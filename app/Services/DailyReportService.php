<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\BookingSource;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProviderAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * DailyReportService — the single source of truth for the printable Daily
 * Report (Z-Report & Employee Summary).
 *
 * This is a CASH/TAKINGS report, not a schedule report. That distinction drives
 * every design decision below:
 *
 *  ── The unit of account is the INVOICE (= one receipt = one transaction).
 *     An invoice always lives on the parent of a linked booking group and its
 *     `total_amount` is the amount actually owed/paid for the whole group, so
 *     counting invoices never double-counts a parent + child pair.
 *
 *  ── The reporting date is the date the money was COLLECTED, not the date the
 *     appointment was scheduled. A Monday booking paid on Tuesday belongs to
 *     Tuesday's report. See {@see resolveCollectedAt()} for the resolution
 *     chain, which exists because `payments` rows are only written by
 *     InvoiceFinalizationService — API cash bookings are flagged paid at
 *     creation time without a payment row.
 *
 *  ── Per-employee revenue is attributed at APPOINTMENT level (provider_id),
 *     because one aggregated invoice may cover several providers. The
 *     invoice-level discount is distributed pro-rata across its appointments so
 *     Σ(employee revenue) == Σ(invoice totals) exactly. See {@see coverageOf()}.
 *
 * Anything that has no collection date at all (cancellations, no-shows, money
 * still outstanding) is necessarily counted by `appointment_date` instead, and
 * the view labels it as such.
 */
class DailyReportService
{
    /** Payment statuses that represent collected money. */
    private const PAID_STATUSES = [
        PaymentStatus::PAID_ONLINE->value,
        PaymentStatus::PAID_ONSTIE_CASH->value,
        PaymentStatus::PAID_ONSTIE_CARD->value,
    ];

    private const CANCELLED_STATUSES = [
        AppointmentStatus::USER_CANCELLED->value,
        AppointmentStatus::ADMIN_CANCELLED->value,
    ];

    /** Money buckets keyed by the PaymentStatus value that feeds them. */
    private const METHOD_BY_STATUS = [
        PaymentStatus::PAID_ONLINE->value      => 'online',
        PaymentStatus::PAID_ONSTIE_CASH->value => 'cash',
        PaymentStatus::PAID_ONSTIE_CARD->value => 'card',
    ];

    /**
     * Build the complete report payload for a date range.
     *
     * @param  string              $from         Y-m-d (inclusive)
     * @param  string              $to           Y-m-d (inclusive; == $from for a single day)
     * @param  array<int,int>      $providerIds  empty = every provider
     * @return array<string,mixed>
     */
    public function build(string $from, string $to, array $providerIds = []): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end   = Carbon::parse($to)->endOfDay();

        // Normalise a reversed range rather than silently returning nothing.
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $providerIds = array_values(array_filter(array_map('intval', $providerIds)));

        // Every collected receipt in the window, each already resolved to a
        // method + timestamp + its per-provider coverage.
        $transactions = $this->collectedTransactions($start, $end, $providerIds);

        // Appointments in the window BY SCHEDULE — the only basis available for
        // things that were never paid (cancelled / no-show / still owed).
        $scheduled = $this->scheduledAppointments($start, $end, $providerIds);

        return [
            'meta'          => $this->meta($start, $end, $providerIds),
            'company'       => $this->company(),
            'sales'         => $this->salesSummary($transactions),
            'totals'        => $this->reportTotals($transactions),
            'vat'           => $this->vatSummary($transactions),
            'discounts'     => $this->discountSummary($transactions),
            'operations'    => $this->operationsSummary($transactions, $scheduled),
            'services'      => $this->servicesBreakdown($transactions),
            'employees'     => $this->employeeBreakdown($transactions, $start, $end, $providerIds),
            'daily'         => $this->dailyBreakdown($transactions, $start, $end),
            'transactions'  => $this->transactionList($transactions),
        ];
    }

    /** The active provider roster, for the filter UI. */
    public function providerOptions(): array
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'provider'))
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Data loading
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Every paid invoice whose money landed inside the window.
     *
     * Two-stage on purpose. SQL cannot filter on the resolved collection date
     * (it lives across three possible sources), so we first pull a deliberate
     * SUPERSET with a three-way OR that covers each source, then filter exactly
     * in PHP. Each OR branch maps to one link in the resolution chain:
     *   1. a successful payment row timestamped in the window
     *   2. the invoice touched in the window (finalisation writes invoice.updated_at)
     *   3. the booking scheduled in the window (catches paid-at-creation API
     *      bookings that have neither a payment row nor a finalisation stamp)
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function collectedTransactions(Carbon $start, Carbon $end, array $providerIds): Collection
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::PAID)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('updated_at', [$start, $end])
                    ->orWhereHas('payments', function ($p) use ($start, $end) {
                        $p->whereIn('status', self::PAID_STATUSES)
                            ->whereBetween('created_at', [$start, $end]);
                    })
                    ->orWhereHas('appointment', function ($a) use ($start, $end) {
                        // whereDate, not whereBetween: `appointment_date` is a
                        // datetime column, so a bare `Y-m-d` upper bound compares
                        // as midnight and silently drops the whole last day.
                        $a->whereDate('appointment_date', '>=', $start->toDateString())
                            ->whereDate('appointment_date', '<=', $end->toDateString());
                    });
            })
            ->with([
                'payments',
                'customer:id,first_name,last_name,email,phone',
                'appointment.provider:id,first_name,last_name,avatar_url',
                'appointment.services_record',
                'appointment.children.provider:id,first_name,last_name,avatar_url',
                'appointment.children.services_record',
            ])
            ->get();

        return $invoices
            ->filter(fn (Invoice $invoice) => $invoice->appointment !== null)
            ->map(function (Invoice $invoice) {
                $collectedAt = $this->resolveCollectedAt($invoice);

                return [
                    'invoice'      => $invoice,
                    'collected_at' => $collectedAt,
                    'method'       => $this->resolveMethod($invoice),
                    'coverage'     => $this->coverageOf($invoice),
                ];
            })
            ->filter(fn (array $t) => $t['collected_at']->betweenIncluded($start, $end))
            // A provider filter keeps a receipt only when at least one of its
            // appointments belongs to a selected provider. Totals then reflect
            // ONLY those providers' share — see coverageOf() trimming below.
            ->when(! empty($providerIds), function (Collection $rows) use ($providerIds) {
                return $rows
                    ->map(function (array $t) use ($providerIds) {
                        $t['coverage'] = array_values(array_filter(
                            $t['coverage'],
                            fn (array $c) => in_array($c['provider_id'], $providerIds, true)
                        ));

                        return $t;
                    })
                    ->filter(fn (array $t) => ! empty($t['coverage']));
            })
            ->sortBy(fn (array $t) => $t['collected_at']->getTimestamp())
            ->values();
    }

    /**
     * Bookings sitting on the calendar in this window, regardless of payment.
     * Used only for cancellations / no-shows / outstanding money, which have no
     * collection date to be filed under.
     *
     * @return Collection<int, Appointment>
     */
    private function scheduledAppointments(Carbon $start, Carbon $end, array $providerIds): Collection
    {
        return Appointment::query()
            // whereDate on both bounds — `appointment_date` is a datetime column
            // and a bare `Y-m-d` upper bound would exclude the final day.
            ->whereDate('appointment_date', '>=', $start->toDateString())
            ->whereDate('appointment_date', '<=', $end->toDateString())
            ->where('created_status', 1)
            ->when(! empty($providerIds), fn ($q) => $q->whereIn('provider_id', $providerIds))
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Resolution helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * When did this invoice's money actually arrive?
     *
     * Ordered by trustworthiness. `payments` is authoritative but only written
     * by the dashboard payment flow; `invoice_data.finalized_at` is written by
     * the same flow as a second witness; `updated_at` approximates it for rows
     * finalised by other paths; `appointment_date` is the last resort for
     * bookings flagged paid at creation (API cash) which have none of the above.
     */
    private function resolveCollectedAt(Invoice $invoice): Carbon
    {
        $payment = $invoice->payments
            ->filter(fn (Payment $p) => in_array($p->status->value, self::PAID_STATUSES, true))
            ->sortBy('created_at')
            ->first();

        if ($payment?->created_at) {
            return $payment->created_at->copy();
        }

        $finalizedAt = $invoice->invoice_data['finalized_at'] ?? null;
        if ($finalizedAt) {
            try {
                return Carbon::parse($finalizedAt);
            } catch (\Throwable) {
                // Malformed stamp — fall through to the next link in the chain.
            }
        }

        if ($invoice->updated_at) {
            return $invoice->updated_at->copy();
        }

        return Carbon::parse($invoice->appointment->appointment_date)->startOfDay();
    }

    /**
     * Which drawer did the money go into: 'cash' | 'card' | 'online'.
     *
     * Same trust order as the timestamp. `invoice_data.payment_type` holds the
     * raw PaymentStatus value as a string (that is what the payment modal posts).
     */
    private function resolveMethod(Invoice $invoice): string
    {
        $payment = $invoice->payments
            ->filter(fn (Payment $p) => in_array($p->status->value, self::PAID_STATUSES, true))
            ->sortBy('created_at')
            ->first();

        if ($payment) {
            return self::METHOD_BY_STATUS[$payment->status->value] ?? 'cash';
        }

        $type = $invoice->invoice_data['payment_type'] ?? null;
        if ($type !== null && isset(self::METHOD_BY_STATUS[(int) $type])) {
            return self::METHOD_BY_STATUS[(int) $type];
        }

        $appointmentStatus = $invoice->appointment?->payment_status?->value;

        return self::METHOD_BY_STATUS[$appointmentStatus] ?? 'cash';
    }

    /**
     * Split one invoice into per-appointment rows carrying the provider, the
     * services delivered and the share of money that belongs to that provider.
     *
     * The discount lives on the invoice alone, so it is spread pro-rata over the
     * appointments by their gross share. This guarantees
     * Σ(coverage amounts) == invoice.total_amount, which is what makes the
     * employee table foot to the sales summary.
     *
     * @return array<int, array<string,mixed>>
     */
    private function coverageOf(Invoice $invoice): array
    {
        $appointments = collect([$invoice->appointment])
            ->merge($invoice->appointment->children ?? collect())
            ->filter();

        $grossTotal = (float) $appointments->sum(fn (Appointment $a) => (float) $a->total_amount);
        $discount   = (float) ($invoice->discount_amount ?? 0);
        $netTotal   = (float) $invoice->total_amount;

        $rows = [];
        $allocated = 0.0;
        $lastIndex = $appointments->count() - 1;

        foreach ($appointments->values() as $index => $appointment) {
            $gross = (float) $appointment->total_amount;

            if ($index === $lastIndex) {
                // The final row absorbs the rounding remainder so the split is
                // exact rather than off by a cent.
                $amount = round($netTotal - $allocated, 2);
            } elseif ($grossTotal > 0) {
                $amount = round($gross * ($netTotal / $grossTotal), 2);
            } else {
                $amount = 0.0;
            }

            $allocated = round($allocated + $amount, 2);

            $rows[] = [
                'appointment_id' => $appointment->id,
                'provider_id'    => (int) $appointment->provider_id,
                'provider_name'  => $appointment->provider?->full_name ?? '—',
                'provider_avatar' => $appointment->provider?->avatar_url,
                'amount'         => $amount,
                'gross'          => $gross,
                'discount_share' => $grossTotal > 0 ? round($gross * ($discount / $grossTotal), 2) : 0.0,
                'services'       => $appointment->services_record
                    ->map(fn ($r) => [
                        'name'  => $r->service_name ?: ($r->service?->name ?? '—'),
                        'price' => (float) $r->price,
                    ])
                    ->all(),
                'customer_key'   => $this->customerKeyOf($appointment),
                // `booking_source` is cast to the BookingSource enum, so it must
                // be compared as an enum — a string comparison here silently
                // reads every booking as in-person. Null (legacy rows predating
                // the column) counts as reception, never as app.
                'source'         => $appointment->booking_source === BookingSource::ONLINE
                    ? 'online'
                    : 'in_person',
            ];
        }

        return $rows;
    }

    /**
     * A stable identity for "one customer", so distinct-customer counts work for
     * guests too. Registered customers key on their id; guests fall back to
     * phone, then email, then name, then the booking itself (worst case: the
     * booking counts as its own customer rather than merging strangers).
     */
    private function customerKeyOf(Appointment $appointment): string
    {
        if ($appointment->customer_id) {
            return 'u:' . $appointment->customer_id;
        }

        foreach (['customer_phone', 'customer_email', 'customer_name'] as $column) {
            $value = trim((string) $appointment->getRawOriginal($column));
            if ($value !== '') {
                return $column . ':' . mb_strtolower($value);
            }
        }

        return 'a:' . $appointment->id;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Sections
    // ─────────────────────────────────────────────────────────────────────

    private function meta(Carbon $start, Carbon $end, array $providerIds): array
    {
        $now = Carbon::now();
        $isSingleDay = $start->toDateString() === $end->toDateString();

        return [
            'from'          => $start->toDateString(),
            'to'            => $end->toDateString(),
            'is_single_day' => $isSingleDay,
            'day_count'     => $start->diffInDays($end) + 1,
            'generated_at'  => $now,
            'generated_by'  => auth()->user()?->full_name ?? '—',
            'provider_ids'  => $providerIds,
            'is_filtered'   => ! empty($providerIds),
            // Deterministic per range + generation moment, mirroring the
            // Z-Report id format used by till systems.
            'report_id'     => sprintf(
                'Z-%s%s-%s',
                $start->format('Ymd'),
                $isSingleDay ? '' : '-' . $end->format('Ymd'),
                $now->format('His')
            ),
            'currency'      => $this->setting('currency') ?? '€',
        ];
    }

    private function company(): array
    {
        return [
            // A salon that has not filled in its details stores empty STRINGS,
            // not nulls, so `get($key, $default)` never falls back on its own —
            // hence setting(), which treats blank as absent.
            'name'       => $this->setting('company_name') ?? config('app.name'),
            'address'    => $this->setting('company_address'),
            'phone'      => $this->setting('company_phone'),
            'email'      => $this->setting('company_email'),
            'tax_number' => $this->setting('company_tax_number'),
        ];
    }

    /** A salon setting, or null when it is unset OR stored blank. */
    private function setting(string $key): ?string
    {
        $value = trim((string) SettingsService::get($key, ''));

        return $value !== '' ? $value : null;
    }

    /** Money in, split by the drawer it landed in. */
    private function salesSummary(Collection $transactions): array
    {
        $buckets = ['cash' => ['count' => 0, 'amount' => 0.0],
                    'card' => ['count' => 0, 'amount' => 0.0],
                    'online' => ['count' => 0, 'amount' => 0.0]];

        foreach ($transactions as $t) {
            $method = $t['method'];
            $buckets[$method]['count']++;
            $buckets[$method]['amount'] = round(
                $buckets[$method]['amount'] + $this->amountOf($t),
                2
            );
        }

        $total = round(array_sum(array_column($buckets, 'amount')), 2);
        $count = array_sum(array_column($buckets, 'count'));

        // Percentages for the donut. Guard against a zero-total day.
        foreach ($buckets as $method => $bucket) {
            $buckets[$method]['percent'] = $total > 0
                ? round($bucket['amount'] / $total * 100, 1)
                : 0.0;
        }

        return [
            'buckets'      => $buckets,
            'total_amount' => $total,
            'total_count'  => $count,
        ];
    }

    private function reportTotals(Collection $transactions): array
    {
        $customers = [];
        foreach ($transactions as $t) {
            foreach ($t['coverage'] as $c) {
                $customers[$c['customer_key']] = true;
            }
        }

        $total = round($transactions->sum(fn (array $t) => $this->amountOf($t)), 2);
        $count = $transactions->count();

        return [
            'transactions'  => $count,
            'receipts'      => $transactions->filter(fn (array $t) => (bool) $t['invoice']->invoice_number)->count(),
            'customers'     => count($customers),
            'services_sold' => $transactions->sum(
                fn (array $t) => collect($t['coverage'])->sum(fn (array $c) => count($c['services']))
            ),
            'avg_ticket'    => $count > 0 ? round($total / $count, 2) : 0.0,
        ];
    }

    /**
     * VAT grouped by rate. Reads the invoice's own stored net/tax rather than
     * recomputing, so the report always matches the printed receipts.
     */
    private function vatSummary(Collection $transactions): array
    {
        $byRate = [];

        foreach ($transactions as $t) {
            /** @var Invoice $invoice */
            $invoice = $t['invoice'];
            $rate = (string) (float) ($invoice->tax_rate ?? 0);

            // A provider-filtered report shows only that provider's slice, so
            // scale the invoice tax by the share of it we are reporting on.
            $share = $this->shareOf($t);

            if (! isset($byRate[$rate])) {
                $byRate[$rate] = ['rate' => (float) $rate, 'net' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
            }

            $byRate[$rate]['net']   = round($byRate[$rate]['net'] + (float) $invoice->subtotal * $share, 2);
            $byRate[$rate]['tax']   = round($byRate[$rate]['tax'] + (float) $invoice->tax_amount * $share, 2);
            $byRate[$rate]['gross'] = round($byRate[$rate]['gross'] + (float) $invoice->total_amount * $share, 2);
        }

        krsort($byRate);

        return [
            'rows'  => array_values($byRate),
            'net'   => round(array_sum(array_column($byRate, 'net')), 2),
            'tax'   => round(array_sum(array_column($byRate, 'tax')), 2),
            'gross' => round(array_sum(array_column($byRate, 'gross')), 2),
        ];
    }

    private function discountSummary(Collection $transactions): array
    {
        $total = 0.0;
        $count = 0;

        foreach ($transactions as $t) {
            $discount = round(
                collect($t['coverage'])->sum(fn (array $c) => $c['discount_share']),
                2
            );

            if ($discount > 0) {
                $total = round($total + $discount, 2);
                $count++;
            }
        }

        $gross = round($transactions->sum(fn (array $t) => $this->amountOf($t)), 2) + $total;

        return [
            'total'      => $total,
            'count'      => $count,
            'percent'    => $gross > 0 ? round($total / $gross * 100, 1) : 0.0,
            'before'     => $gross,
        ];
    }

    /**
     * Non-money operational context. Source split comes from the paid receipts;
     * cancellations / no-shows / outstanding necessarily come from the schedule,
     * because unpaid bookings have no collection date to be filed under.
     */
    private function operationsSummary(Collection $transactions, Collection $scheduled): array
    {
        $online = 0;
        $inPerson = 0;
        foreach ($transactions as $t) {
            foreach ($t['coverage'] as $c) {
                $c['source'] === 'online' ? $online++ : $inPerson++;
            }
        }

        $cancelled = $scheduled->filter(
            fn (Appointment $a) => in_array($a->status->value, self::CANCELLED_STATUSES, true)
        );
        $noShow = $scheduled->filter(
            fn (Appointment $a) => $a->status->value === AppointmentStatus::NO_SHOW->value
        );
        $outstanding = $scheduled
            ->reject(fn (Appointment $a) => in_array($a->status->value, self::CANCELLED_STATUSES, true))
            ->filter(fn (Appointment $a) => $a->payment_status->value === PaymentStatus::PENDING->value);

        return [
            'source_online'      => $online,
            'source_in_person'   => $inPerson,
            'cancelled'          => $cancelled->count(),
            'no_show'            => $noShow->count(),
            'outstanding_count'  => $outstanding->count(),
            'outstanding_amount' => round($outstanding->sum(fn (Appointment $a) => (float) $a->total_amount), 2),
            'scheduled_total'    => $scheduled->count(),
        ];
    }

    /**
     * Salon-wide service tally. Built from the appointment_services snapshot so
     * historical names and prices stay accurate even after a service is renamed
     * or repriced.
     */
    private function servicesBreakdown(Collection $transactions): array
    {
        $tally = [];

        foreach ($transactions as $t) {
            foreach ($t['coverage'] as $c) {
                foreach ($c['services'] as $service) {
                    $name = $service['name'];
                    $tally[$name] ??= ['name' => $name, 'count' => 0, 'revenue' => 0.0];
                    $tally[$name]['count']++;
                    $tally[$name]['revenue'] = round($tally[$name]['revenue'] + $service['price'], 2);
                }
            }
        }

        $totalRevenue = array_sum(array_column($tally, 'revenue'));

        foreach ($tally as $name => $row) {
            $tally[$name]['percent'] = $totalRevenue > 0
                ? round($row['revenue'] / $totalRevenue * 100, 1)
                : 0.0;
        }

        $rows = array_values($tally);
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $rows;
    }

    /**
     * The employee summary table + the per-employee detail cards.
     *
     * Working time is REAL attendance (provider_attendances check-in/out), not
     * the planned shift — a provider who never clocked in shows "—" rather than
     * a fictional 09:00–17:00.
     */
    private function employeeBreakdown(Collection $transactions, Carbon $start, Carbon $end, array $providerIds): array
    {
        $rows = [];

        foreach ($transactions as $t) {
            $method = $t['method'];

            foreach ($t['coverage'] as $c) {
                $pid = $c['provider_id'];

                $rows[$pid] ??= [
                    'provider_id'   => $pid,
                    'provider_name' => $c['provider_name'],
                    'avatar'        => $c['provider_avatar'],
                    'appointments'  => 0,
                    'services'      => 0,
                    'cash'          => 0.0,
                    'card'          => 0.0,
                    'online'        => 0.0,
                    'total'         => 0.0,
                    'customer_keys' => [],
                    'service_tally' => [],
                ];

                $rows[$pid]['appointments']++;
                $rows[$pid]['services'] += count($c['services']);
                $rows[$pid][$method] = round($rows[$pid][$method] + $c['amount'], 2);
                $rows[$pid]['total']  = round($rows[$pid]['total'] + $c['amount'], 2);
                $rows[$pid]['customer_keys'][$c['customer_key']] = true;

                foreach ($c['services'] as $service) {
                    $rows[$pid]['service_tally'][$service['name']] =
                        ($rows[$pid]['service_tally'][$service['name']] ?? 0) + 1;
                }
            }
        }

        // Fold in providers with zero takings so an idle employee is visible
        // rather than silently absent from the report.
        foreach ($this->rosterFor($providerIds) as $provider) {
            $rows[$provider->id] ??= [
                'provider_id'   => $provider->id,
                'provider_name' => $provider->full_name,
                'avatar'        => $provider->avatar_url,
                'appointments'  => 0,
                'services'      => 0,
                'cash'          => 0.0,
                'card'          => 0.0,
                'online'        => 0.0,
                'total'         => 0.0,
                'customer_keys' => [],
                'service_tally' => [],
            ];
        }

        $attendance = $this->attendanceFor(array_keys($rows), $start, $end);

        foreach ($rows as $pid => $row) {
            arsort($row['service_tally']);

            $rows[$pid]['customers']  = count($row['customer_keys']);
            $rows[$pid]['avg_ticket'] = $row['appointments'] > 0
                ? round($row['total'] / $row['appointments'], 2)
                : 0.0;
            $rows[$pid]['top_service'] = array_key_first($row['service_tally']);
            $rows[$pid]['attendance']  = $attendance[$pid] ?? null;

            unset($rows[$pid]['customer_keys'], $rows[$pid]['service_tally']);
        }

        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'rows'   => $rows,
            'totals' => [
                'appointments' => array_sum(array_column($rows, 'appointments')),
                'services'     => array_sum(array_column($rows, 'services')),
                'cash'         => round(array_sum(array_column($rows, 'cash')), 2),
                'card'         => round(array_sum(array_column($rows, 'card')), 2),
                'online'       => round(array_sum(array_column($rows, 'online')), 2),
                'total'        => round(array_sum(array_column($rows, 'total')), 2),
                'avg_ticket'   => array_sum(array_column($rows, 'appointments')) > 0
                    ? round(array_sum(array_column($rows, 'total')) / array_sum(array_column($rows, 'appointments')), 2)
                    : 0.0,
            ],
        ];
    }

    /** @return Collection<int, User> */
    private function rosterFor(array $providerIds): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'provider'))
            ->where('is_active', true)
            ->when(! empty($providerIds), fn ($q) => $q->whereIn('id', $providerIds))
            ->get(['id', 'first_name', 'last_name', 'avatar_url']);
    }

    /**
     * Real clocked hours per provider over the window.
     *
     * `first`/`last` show the span of the working day (matching the reference
     * report's "Working Time" line); `minutes` is the sum of closed sessions
     * only, so a session left open never inflates the figure — `open_sessions`
     * surfaces that instead.
     *
     * @return array<int, array<string,mixed>>
     */
    private function attendanceFor(array $providerIds, Carbon $start, Carbon $end): array
    {
        if (empty($providerIds)) {
            return [];
        }

        $sessions = ProviderAttendance::query()
            ->whereIn('user_id', $providerIds)
            // whereDate on both bounds — `work_date` is cast to a date but stored
            // as a datetime, so a bare `Y-m-d` upper bound would drop the last day.
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->orderBy('check_in_at')
            ->get();

        $out = [];

        foreach ($sessions->groupBy('user_id') as $userId => $userSessions) {
            $closed = $userSessions->filter(fn (ProviderAttendance $s) => $s->check_out_at !== null);

            $out[(int) $userId] = [
                'first'         => $userSessions->first()?->check_in_at?->format('H:i'),
                'last'          => $closed->sortByDesc('check_out_at')->first()?->check_out_at?->format('H:i'),
                'minutes'       => (int) $closed->sum(fn (ProviderAttendance $s) => $s->duration_minutes ?? 0),
                'sessions'      => $userSessions->count(),
                'open_sessions' => $userSessions->count() - $closed->count(),
            ];
        }

        return $out;
    }

    /**
     * Day-by-day takings. Only meaningful for a multi-day range; the view hides
     * it for a single day where it would just repeat the sales summary.
     */
    private function dailyBreakdown(Collection $transactions, Carbon $start, Carbon $end): array
    {
        $byDay = [];

        // Seed every day in the range so zero-takings days are visible as gaps
        // rather than disappearing from the table.
        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $byDay[$day->toDateString()] = [
                'date'   => $day->toDateString(),
                'count'  => 0,
                'cash'   => 0.0,
                'card'   => 0.0,
                'online' => 0.0,
                'total'  => 0.0,
            ];
        }

        foreach ($transactions as $t) {
            $key = $t['collected_at']->toDateString();
            if (! isset($byDay[$key])) {
                continue;
            }

            $amount = $this->amountOf($t);
            $byDay[$key]['count']++;
            $byDay[$key][$t['method']] = round($byDay[$key][$t['method']] + $amount, 2);
            $byDay[$key]['total']      = round($byDay[$key]['total'] + $amount, 2);
        }

        return array_values($byDay);
    }

    /** The audit appendix: one line per collected receipt. */
    private function transactionList(Collection $transactions): array
    {
        return $transactions->map(function (array $t) {
            /** @var Invoice $invoice */
            $invoice = $t['invoice'];
            $providers = collect($t['coverage'])->pluck('provider_name')->unique()->implode(', ');

            return [
                'invoice_number' => $invoice->invoice_number ?: '—',
                'time'           => $t['collected_at']->format('H:i'),
                'date'           => $t['collected_at']->toDateString(),
                'customer'       => $invoice->appointment->customer_name ?: '—',
                'provider'       => $providers ?: '—',
                'method'         => $t['method'],
                'amount'         => $this->amountOf($t),
            ];
        })->all();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Small helpers
    // ─────────────────────────────────────────────────────────────────────

    /** The money this report counts for a transaction (post provider-filter). */
    private function amountOf(array $transaction): float
    {
        return round(collect($transaction['coverage'])->sum(fn (array $c) => $c['amount']), 2);
    }

    /**
     * How much of the invoice this report is counting, 0..1. Always 1 for an
     * unfiltered report; below 1 when a provider filter trimmed the coverage.
     */
    private function shareOf(array $transaction): float
    {
        $invoiceTotal = (float) $transaction['invoice']->total_amount;

        if ($invoiceTotal <= 0) {
            return 1.0;
        }

        return min(1.0, $this->amountOf($transaction) / $invoiceTotal);
    }
}
