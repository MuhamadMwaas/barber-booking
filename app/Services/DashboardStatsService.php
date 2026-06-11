<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * DashboardStatsService — the single source of truth for the StaffStats tab.
 *
 * Everything the Daily Statistics screen needs is computed here so the Livewire
 * component stays a thin orchestrator (isolated logic, as required). No Blade,
 * no request state, no presentation — just numbers in a predictable shape.
 *
 * Scoping:
 *   - statsForDate($date, $providerId)  → one metric bundle for a single
 *     provider (the "my stats" view) OR the whole salon when $providerId is null.
 *   - perProviderBreakdown($date)       → the same bundle per provider, so the
 *     admin/manager view can show a per-provider table.
 *
 * Conventions (kept identical to DashboardService so numbers reconcile):
 *   - A "real" booking has created_status = 1. Abandoned drafts (0) never count.
 *   - The canonical day column is `appointment_date` (whereDate), matching the
 *     timeline and the calendar counts.
 *   - Revenue is attributed to each appointment's own provider_id. Linked
 *     (parent/child) bookings each carry only their own services' total_amount
 *     — the aggregation lives on the invoice, NOT on appointment.total_amount —
 *     so summing per-appointment totals never double-counts.
 *   - "Paid" = payment_status in {PAID_ONLINE, PAID_ONSTIE_CASH, PAID_ONSTIE_CARD}.
 */
class DashboardStatsService
{
    /** Payment statuses that count as collected money. */
    private const PAID_STATUSES = [
        PaymentStatus::PAID_ONLINE->value,
        PaymentStatus::PAID_ONSTIE_CASH->value,
        PaymentStatus::PAID_ONSTIE_CARD->value,
    ];

    /** Statuses that mean the booking was cancelled (admin or customer). */
    private const CANCELLED_STATUSES = [
        AppointmentStatus::USER_CANCELLED->value,
        AppointmentStatus::ADMIN_CANCELLED->value,
    ];

    /**
     * The metric bundle for one scope on a given day.
     *
     * @param  string    $date        Y-m-d
     * @param  int|null  $providerId  null = whole salon; otherwise one provider
     * @return array<string, mixed>
     */
    public function statsForDate(string $date, ?int $providerId = null): array
    {
        $appointments = $this->dayAppointments($date, $providerId);

        return $this->computeBundle($appointments);
    }

    /**
     * One metric bundle per active provider (plus any provider who has bookings
     * that day even if now inactive), so no revenue is ever orphaned.
     *
     * @return array<int, array<string, mixed>>  ordered by paid revenue desc
     */
    public function perProviderBreakdown(string $date): array
    {
        $appointments = $this->dayAppointments($date, null);
        $byProvider = $appointments->groupBy('provider_id');

        // Start from the active-provider roster so providers with zero bookings
        // still appear (an admin wants to see who was idle, too).
        $providers = User::whereHas('roles', fn ($q) => $q->where('name', 'provider'))
            ->where('is_active', true)
            ->get(['id', 'first_name', 'last_name', 'avatar_url'])
            ->keyBy('id');

        // Fold in any provider that has bookings today but is missing from the
        // active roster (deactivated mid-day, role changed, etc.).
        foreach ($byProvider->keys() as $pid) {
            if (! $providers->has($pid)) {
                $user = User::find($pid, ['id', 'first_name', 'last_name', 'avatar_url']);
                if ($user) {
                    $providers->put($pid, $user);
                }
            }
        }

        $rows = $providers->map(function (User $provider) use ($byProvider) {
            $appts = $byProvider->get($provider->id, collect());
            $bundle = $this->computeBundle($appts);

            return array_merge($bundle, [
                'provider_id' => $provider->id,
                'provider_name' => $provider->full_name,
                'provider_avatar' => $provider->avatar_url,
            ]);
        })->values();

        // Most productive first; idle providers sink to the bottom.
        return $rows
            ->sortByDesc(fn ($row) => $row['paid_revenue'])
            ->values()
            ->all();
    }

    /**
     * Load every real booking for the day (all statuses, including cancelled and
     * no-show — the caller needs them to count cancellations). Relations needed
     * for the services breakdown are eager-loaded once to avoid N+1.
     */
    private function dayAppointments(string $date, ?int $providerId): Collection
    {
        $query = Appointment::query()
            ->with(['services_record.service'])
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1);

        if ($providerId !== null) {
            $query->where('provider_id', $providerId);
        }

        return $query->get();
    }

    /**
     * Reduce a set of appointments into the full metric bundle the UI renders.
     *
     * "now"-relative cards (in_progress / upcoming) are computed against the
     * real clock, so they are meaningful when viewing today and naturally read
     * as 0 / all-upcoming for past / future days.
     */
    private function computeBundle(Collection $appointments): array
    {
        $now = Carbon::now();

        // Non-cancelled = the bookings that actually occupy the day.
        $active = $appointments->reject(
            fn (Appointment $a) => in_array($a->status->value, self::CANCELLED_STATUSES, true)
        );

        $completed = $active->filter(
            fn (Appointment $a) => $a->status->value === AppointmentStatus::COMPLETED->value
        );
        $pending = $active->filter(
            fn (Appointment $a) => $a->status->value === AppointmentStatus::PENDING->value
        );
        $noShow = $active->filter(
            fn (Appointment $a) => $a->status->value === AppointmentStatus::NO_SHOW->value
        );

        $inProgressNow = $pending->filter(function (Appointment $a) use ($now) {
            return $a->start_time && $a->end_time
                && $a->start_time->lte($now) && $a->end_time->gte($now);
        });

        $upcoming = $pending->filter(
            fn (Appointment $a) => $a->start_time && $a->start_time->gt($now)
        );

        $cancelled = $appointments->filter(
            fn (Appointment $a) => in_array($a->status->value, self::CANCELLED_STATUSES, true)
        );

        // ---- Money ----
        $paid = $active->filter(
            fn (Appointment $a) => in_array($a->payment_status->value, self::PAID_STATUSES, true)
        );
        $paidRevenue = (float) $paid->sum(fn (Appointment $a) => (float) $a->total_amount);

        $outstanding = (float) $active
            ->where('payment_status.value', PaymentStatus::PENDING->value)
            ->sum(fn (Appointment $a) => (float) $a->total_amount);

        $avgTicket = $paid->count() > 0 ? $paidRevenue / $paid->count() : 0.0;

        // ---- Source: app (online) vs reception (in_person) ----
        $online = $active->filter(
            fn (Appointment $a) => $this->sourceOf($a) === 'online'
        )->count();
        $inPerson = $active->count() - $online;

        // ---- Booked working time (sum of service-appointment durations) ----
        $bookedMinutes = (int) $active->sum(fn (Appointment $a) => (int) $a->duration_minutes);

        return [
            'total_bookings'   => $active->count(),
            'completed'        => $completed->count(),
            'in_progress_now'  => $inProgressNow->count(),
            'upcoming'         => $upcoming->count(),
            'cancelled'        => $cancelled->count(),
            'no_show'          => $noShow->count(),

            'paid_revenue'     => $paidRevenue,
            'paid_count'       => $paid->count(),
            'outstanding'      => $outstanding,
            'avg_ticket'       => $avgTicket,

            'source_online'    => $online,
            'source_in_person' => $inPerson,

            'booked_minutes'   => $bookedMinutes,

            'services'         => $this->servicesBreakdown($active),
        ];
    }

    /**
     * Per-service tally for the day: how many of each service were delivered and
     * the gross they brought in. Built from the appointment_services snapshot so
     * historical names/prices stay accurate. Sorted by count desc.
     *
     * @return array<int, array{name: string, count: int, revenue: float}>
     */
    private function servicesBreakdown(Collection $appointments): array
    {
        $tally = [];

        foreach ($appointments as $appointment) {
            foreach ($appointment->services_record as $record) {
                $name = $record->service_name ?: ($record->service?->name ?? 'Service');

                if (! isset($tally[$name])) {
                    $tally[$name] = ['name' => $name, 'count' => 0, 'revenue' => 0.0];
                }

                $tally[$name]['count']++;
                $tally[$name]['revenue'] += (float) $record->price;
            }
        }

        usort($tally, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($tally);
    }

    /**
     * Normalise the booking source to 'online' | 'in_person'. The column was
     * added later with an 'in_person' default, so an absent/legacy value reads
     * as reception, never as app.
     */
    private function sourceOf(Appointment $appointment): string
    {
        return $appointment->getAttribute('booking_source') === 'online'
            ? 'online'
            : 'in_person';
    }
}
