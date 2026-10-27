<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\LeaveReassignmentRequiredException;
use App\Mail\BookingCancelledProviderUnavailableMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\User;
use App\Support\DateFormat;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * THE single way a leave is written — every surface that grants or edits one
 * (StaffDashboard, the provider page's two leave actions, the leaves relation
 * manager) comes through grant().
 *
 * A leave is only granted over an EMPTY calendar. Open bookings inside it are
 * handed to free colleagues first — same time, same price, only the provider
 * changes — and the ones nobody can take are cancelled, with the customer told
 * why. Before this, saving a leave simply hid the provider and orphaned every
 * booking under it.
 *
 * Flow: plan() is a pure read the UI shows; grant() applies it. grant()
 * validates the leave itself (callers' forms are not trusted to have), takes
 * the provider locks, rebuilds the plan under them and refuses
 * (LeaveReassignmentRequiredException) unless its signature is exactly the one
 * the user confirmed — the signature covers the leave and every booking's
 * time, services, customer and destination, so a confirmation is only ever
 * applied to what the user actually saw.
 */
class LeaveReassignmentService
{
    public const ACTION_MOVE = 'move';

    public const ACTION_CANCEL = 'cancel';

    /** No colleague is free and the booking is paid, so it cannot be cancelled either. */
    public const ACTION_BLOCKED = 'blocked';

    /**
     * Search budget for one group of overlapping bookings. Such groups only
     * exist through forced double bookings and are tiny in practice; the cap
     * only keeps a pathological day from hanging the request — the best
     * assignment found so far is used, never a worse one than plain greedy.
     */
    private const SEARCH_NODE_LIMIT = 20000;

    private const PAID_STATES = [
        PaymentStatus::PAID_ONLINE,
        PaymentStatus::PAID_ONSTIE_CASH,
        PaymentStatus::PAID_ONSTIE_CARD,
    ];

    public function __construct(
        private readonly BookingLockService $lockService,
        private readonly AppointmentCancellationService $cancellationService,
    ) {}

    /**
     * The shape rules every leave must pass, on raw input — shared with the
     * StaffDashboard modal so both report the same message.
     *
     * Times that run backwards are rejected rather than read as crossing
     * midnight: the salon does not trade through midnight, so 22:00 → 02:00 is
     * a typo, not an overnight absence.
     *
     * @return string|null the error message, or null when the shape is valid.
     */
    public static function validationError(
        int $type,
        ?string $startDate,
        ?string $endDate,
        ?string $startTime,
        ?string $endTime,
    ): ?string {
        if (! in_array($type, [ProviderTimeOff::TYPE_HOURLY, ProviderTimeOff::TYPE_FULL_DAY], true)) {
            return __('dashboard.time_off_modal.type_invalid');
        }

        if (empty($startDate)) {
            return __('dashboard.time_off_modal.start_date_required');
        }

        if (! self::isDate($startDate) || (! empty($endDate) && ! self::isDate($endDate))) {
            return __('dashboard.time_off_modal.date_invalid');
        }

        if (! empty($endDate) && $endDate < $startDate) {
            return __('dashboard.time_off_modal.end_date_before_start');
        }

        if ($type !== ProviderTimeOff::TYPE_HOURLY) {
            return null;
        }

        if (empty($startTime) || empty($endTime)) {
            return __('dashboard.time_off_modal.times_required');
        }

        if (! self::isTime($startTime) || ! self::isTime($endTime)) {
            return __('dashboard.time_off_modal.times_required');
        }

        if (substr($endTime, 0, 5) <= substr($startTime, 0, 5)) {
            return __('dashboard.time_off_modal.end_time_after_start');
        }

        return null;
    }

    /**
     * The leave as a whole: the shape above plus the references it points at.
     *
     * @throws InvalidArgumentException with a message fit to show the user.
     */
    public function assertValid(ProviderTimeOff $leave): void
    {
        if (! $leave->user_id || ! User::whereKey($leave->user_id)->exists()) {
            throw new InvalidArgumentException(__('dashboard.time_off_modal.provider_required'));
        }

        if (! $leave->reason_id || ! ReasonLeave::whereKey($leave->reason_id)->exists()) {
            throw new InvalidArgumentException(__('dashboard.time_off_modal.reason_required'));
        }

        $error = self::validationError(
            (int) $leave->type,
            $this->rawDate($leave, 'start_date'),
            $this->rawDate($leave, 'end_date'),
            $this->rawTime($leave, 'start_time'),
            $this->rawTime($leave, 'end_time'),
        );

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * Open bookings of the leave's provider that the leave would cover.
     *
     * "Open" = confirmed and PENDING; completed, paid-out or cancelled bookings
     * are history and never stand in a leave's way.
     *
     * Neither is a booking whose start time has already come — one still
     * PENDING because nobody marked the no-show, or one in progress. There is
     * nothing to hand over, and telling the customer that a past appointment
     * was cancelled would only confuse them. Staff close those by hand.
     *
     * @return Collection<int, Appointment>
     */
    public function conflicts(ProviderTimeOff $leave): Collection
    {
        [$from, $to] = $this->range($leave);

        return Appointment::with(['services_record', 'customer'])
            ->where('provider_id', $leave->user_id)
            ->where('created_status', 1)
            ->where('status', AppointmentStatus::PENDING->value)
            ->where('start_time', '>', now())
            ->whereDate('appointment_date', '>=', $from)
            ->whereDate('appointment_date', '<=', $to)
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (Appointment $appointment) => $leave->blocksWindow($appointment->start_time, $appointment->end_time))
            ->values();
    }

    /**
     * What would happen to each booking the leave covers. Writes nothing.
     *
     * A colleague can take a booking when they offer every one of its services,
     * work that day across its whole time, are not on leave then and are free.
     *
     * Keeping bookings comes first, balance second: bookings that overlap each
     * other (only possible through forced double bookings) compete for the same
     * colleagues, so such a group is searched for the assignment that moves the
     * MOST of them — a plain first-come pick could cancel a booking that a
     * different hand-out would have saved. Among equally good assignments, and
     * for every booking without competition, the colleague with the fewest
     * bookings that day wins (then by name), so the load spreads.
     *
     * @return array{rows: array<int, array<string, mixed>>, signature: string, moves: int, cancellations: int, blocked: int}
     *
     * @throws InvalidArgumentException when the leave itself is not valid.
     */
    public function plan(ProviderTimeOff $leave): array
    {
        $this->assertValid($leave);

        $conflicts = $this->conflicts($leave);

        if ($conflicts->isEmpty()) {
            return $this->summarise($leave, []);
        }

        [$from, $to] = $this->range($leave);
        $candidates = $this->candidates($leave);
        $candidateIds = $candidates->pluck('id')->all();

        $shifts = ProviderScheduledWork::whereIn('user_id', $candidateIds)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->get()
            ->groupBy(fn (ProviderScheduledWork $shift) => $shift->user_id.':'.$shift->day_of_week);

        $offered = DB::table('provider_service')
            ->whereIn('provider_id', $candidateIds)
            ->where('is_active', true)
            ->get(['provider_id', 'service_id'])
            ->map(fn ($link) => $link->provider_id.':'.$link->service_id)
            ->flip();

        $leaves = ProviderTimeOff::whereIn('user_id', $candidateIds)
            ->touchingRange($from, $to)
            ->get()
            ->groupBy('user_id');

        // [providerId => [[start, end], ...]] — what each colleague already has.
        $busy = [];
        // ["providerId:Y-m-d" => count] — the "least busy that day" tie-break.
        $load = [];

        Appointment::whereIn('provider_id', $candidateIds)
            ->blocksProviderTime()
            ->whereDate('appointment_date', '>=', $from)
            ->whereDate('appointment_date', '<=', $to)
            ->get(['id', 'provider_id', 'appointment_date', 'start_time', 'end_time'])
            ->each(function (Appointment $booked) use (&$busy, &$load) {
                $busy[$booked->provider_id][] = [$booked->start_time, $booked->end_time];
                $key = $booked->provider_id.':'.$booked->start_time->format('Y-m-d');
                $load[$key] = ($load[$key] ?? 0) + 1;
            });

        // Who could take each booking, judged against the calendar as it is.
        // Competition between the leave's own bookings is resolved below.
        $eligible = [];

        foreach ($conflicts as $appointment) {
            $start = $appointment->start_time;
            $end = $appointment->end_time;
            $serviceIds = $appointment->services_record->pluck('service_id')->filter()->unique();

            $eligible[$appointment->id] = $candidates
                ->filter(function (User $candidate) use ($start, $end, $serviceIds, $shifts, $offered, $leaves, $busy) {
                    if ($serviceIds->contains(fn ($serviceId) => ! $offered->has($candidate->id.':'.$serviceId))) {
                        return false;
                    }

                    $worksThen = collect($shifts->get($candidate->id.':'.$start->dayOfWeek, []))
                        ->contains(fn (ProviderScheduledWork $shift) => $this->shiftCovers($shift, $start, $end));

                    if (! $worksThen) {
                        return false;
                    }

                    $onLeave = collect($leaves->get($candidate->id, []))
                        ->contains(fn (ProviderTimeOff $timeOff) => $timeOff->blocksWindow($start, $end));

                    if ($onLeave) {
                        return false;
                    }

                    return ! collect($busy[$candidate->id] ?? [])
                        ->contains(fn (array $slot) => $slot[0]->lt($end) && $slot[1]->gt($start));
                })
                ->values();
        }

        /** @var array<int, User|null> $targets appointment id => colleague */
        $targets = [];

        foreach ($this->overlapGroups($conflicts) as $group) {
            $assignment = $this->assignGroup($group, $eligible, $load);

            foreach ($group as $appointment) {
                $target = $assignment[$appointment->id] ?? null;
                $targets[$appointment->id] = $target;

                if ($target) {
                    $key = $target->id.':'.$appointment->start_time->format('Y-m-d');
                    $load[$key] = ($load[$key] ?? 0) + 1;
                }
            }
        }

        $rows = $conflicts
            ->map(fn (Appointment $appointment) => $this->row($appointment, $targets[$appointment->id] ?? null))
            ->all();

        return $this->summarise($leave, $rows);
    }

    /**
     * Save the leave — after moving and cancelling the bookings it covers.
     *
     * $leave is an unsaved (or, for an edit, dirty) ProviderTimeOff. Without a
     * $confirmedSignature it is only saved when it covers no open booking;
     * otherwise the plan comes back in the exception for the user to confirm.
     * With one, the plan is rebuilt under the locks and applied only if it
     * still has that exact signature.
     *
     * $mayCancel is the caller's answer to "may this user cancel customers'
     * bookings?". It is checked against the plan built under the locks, so it
     * holds whatever the client sent.
     *
     * @return array{time_off: ProviderTimeOff, moved: int, cancelled: int}
     *
     * @throws LeaveReassignmentRequiredException
     * @throws InvalidArgumentException when the leave itself is not valid.
     * @throws AuthorizationException when the plan cancels and $mayCancel is false.
     */
    public function grant(ProviderTimeOff $leave, ?string $confirmedSignature = null, bool $mayCancel = true): array
    {
        $this->assertValid($leave);

        return DB::transaction(function () use ($leave, $confirmedSignature, $mayCancel) {
            // The leave provider, so no booking lands on them while we decide,
            // and every colleague who could take one, so the plan rebuilt below
            // stays true until the moves are written.
            $this->lockService->lockUsers([
                $leave->user_id,
                ...$this->candidates($leave)->pluck('id')->all(),
            ]);

            $plan = $this->plan($leave);

            if ($plan['rows'] !== []) {
                if ($confirmedSignature === null || ! hash_equals($plan['signature'], $confirmedSignature)) {
                    throw new LeaveReassignmentRequiredException($plan, changed: $confirmedSignature !== null);
                }

                if ($plan['blocked'] > 0) {
                    throw new LeaveReassignmentRequiredException($plan);
                }

                if ($plan['cancellations'] > 0 && ! $mayCancel) {
                    throw new AuthorizationException(__('dashboard.permission_denied'));
                }
            }

            $moved = 0;
            $cancelled = 0;

            foreach ($plan['rows'] as $row) {
                $appointment = $this->lockStillOpen($row['appointment_id'], $leave);

                if ($row['action'] === self::ACTION_MOVE) {
                    // Only the provider changes: time, price and invoice stay as
                    // the customer agreed them.
                    $appointment->update([
                        'provider_id' => $row['target_provider_id'],
                        'reassigned_from_provider_id' => $leave->user_id,
                    ]);
                    $moved++;

                    continue;
                }

                $cancelledAppointment = $this->cancellationService->cancel(
                    $appointment,
                    AppointmentStatus::ADMIN_CANCELLED,
                    'Provider unavailable (leave)',
                );
                $cancelled++;

                // After the OUTERMOST commit — never for a cancellation a caller's
                // enclosing transaction later rolls back — and dropped on rollback.
                DB::afterCommit(fn () => $this->notifyCancelledCustomer($cancelledAppointment));
            }

            $leave->save();

            return ['time_off' => $leave, 'moved' => $moved, 'cancelled' => $cancelled];
        });
    }

    /**
     * Lock the booking's group the way every other writer does — root first,
     * then the group by id (see AppointmentCancellationService) — and make sure
     * it is still the open booking the plan was built on.
     *
     * Payment locks these rows but not the provider rows grant() holds, so a
     * booking can be paid between the plan and the write; moving it then would
     * hand finished, paid work to a colleague.
     *
     * @throws LeaveReassignmentRequiredException with the fresh plan.
     */
    private function lockStillOpen(int $appointmentId, ProviderTimeOff $leave): Appointment
    {
        $rootId = Appointment::query()
            ->whereKey($appointmentId)
            ->value(DB::raw('COALESCE(parent_appointment_id, id)'));

        Appointment::query()->whereKey($rootId)->lockForUpdate()->get(['id']);

        $appointment = Appointment::query()
            ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_appointment_id', $rootId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->firstWhere('id', $appointmentId);

        if (! $appointment
            || $appointment->status !== AppointmentStatus::PENDING
            || (int) $appointment->provider_id !== (int) $leave->user_id) {
            throw new LeaveReassignmentRequiredException($this->plan($leave), changed: true);
        }

        return $appointment;
    }

    /**
     * Email + in-app notice that the booking was cancelled because its provider
     * is no longer available. Guests with an email get the email only; a
     * customer without an account or email gets nothing we could send.
     *
     * Each channel stands alone: a mail that cannot be queued must not cost
     * the customer the app notice, and the other way round.
     */
    private function notifyCancelledCustomer(Appointment $appointment): void
    {
        try {
            $appointment->loadMissing(['services_record', 'provider', 'customer']);
            $locale = $this->customerLocale($appointment);
        } catch (\Throwable $e) {
            $this->logNoticeFailure($appointment, 'prepare', $e);

            return;
        }

        if (! empty($appointment->customer_email)) {
            try {
                $mailService = app(BookingMailService::class);

                Mail::to($appointment->customer_email)->queue(new BookingCancelledProviderUnavailableMail(
                    appointment: $appointment,
                    companyName: $mailService->companyName(),
                    currency: $mailService->currency(),
                    locale: $locale,
                ));
            } catch (\Throwable $e) {
                $this->logNoticeFailure($appointment, 'mail', $e);
            }
        }

        if ($appointment->customer) {
            try {
                app(NotificationService::class)->sendNotificationToUser(
                    $appointment->customer,
                    'notification.booking_cancelled_provider_unavailable_title',
                    'notification.booking_cancelled_provider_unavailable_body',
                    [
                        'number' => $appointment->number,
                        'date' => $appointment->start_time->format('d.m.Y'),
                        'time' => $appointment->start_time->format('H:i'),
                    ],
                    [
                        'type' => 'booking_cancelled_provider_unavailable',
                        'appointment_id' => $appointment->id,
                    ],
                    $locale,
                );
            } catch (\Throwable $e) {
                $this->logNoticeFailure($appointment, 'app', $e);
            }
        }
    }

    private function logNoticeFailure(Appointment $appointment, string $channel, \Throwable $e): void
    {
        Log::error('Leave cancellation notice failed', [
            'appointment_id' => $appointment->id,
            'channel' => $channel,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * users.locale is never filled, so the language the customer actually uses
     * is the one captured on their reminder when they booked; else German.
     */
    private function customerLocale(Appointment $appointment): string
    {
        $captured = AppointmentReminder::where('appointment_id', $appointment->id)
            ->whereNotNull('locale')
            ->latest('id')
            ->value('locale');

        return BookingMailService::customerLocale($captured);
    }

    /**
     * Active providers who could take over: the leave provider's colleagues in
     * exactly the same branch — the customer still walks into the same salon.
     * A provider with no branch only matches others with no branch, never the
     * whole chain.
     *
     * @return Collection<int, User>
     */
    private function candidates(ProviderTimeOff $leave): Collection
    {
        $branchId = User::whereKey($leave->user_id)->value('branch_id');

        return User::whereHas('roles', fn ($query) => $query->where('name', 'provider'))
            ->where('is_active', true)
            ->whereKeyNot($leave->user_id)
            ->when(
                $branchId,
                fn ($query) => $query->where('branch_id', $branchId),
                fn ($query) => $query->whereNull('branch_id'),
            )
            ->get(['id', 'first_name', 'last_name', 'branch_id']);
    }

    /**
     * Split the bookings (sorted by start) into groups whose members overlap,
     * directly or through a chain. Bookings in different groups never compete
     * for a colleague's time, so each group can be solved on its own.
     *
     * @param  Collection<int, Appointment>  $conflicts
     * @return array<int, array<int, Appointment>>
     */
    private function overlapGroups(Collection $conflicts): array
    {
        $groups = [];
        $groupEnd = null;

        foreach ($conflicts as $appointment) {
            if ($groupEnd !== null && $appointment->start_time->lt($groupEnd)) {
                $groups[array_key_last($groups)][] = $appointment;
                $groupEnd = $appointment->end_time->gt($groupEnd) ? $appointment->end_time : $groupEnd;

                continue;
            }

            $groups[] = [$appointment];
            $groupEnd = $appointment->end_time;
        }

        return $groups;
    }

    /**
     * The assignment of one overlap group that moves the most bookings.
     *
     * Depth-first, most-constrained booking first, least-busy colleague first —
     * so the first assignment found is the balanced one, and a full one stops
     * the search at once. A colleague never gets two bookings of the group that
     * overlap each other.
     *
     * @param  array<int, Appointment>  $group
     * @param  array<int, Collection<int, User>>  $eligible
     * @param  array<string, int>  $load
     * @return array<int, User|null> appointment id => colleague
     */
    private function assignGroup(array $group, array $eligible, array $load): array
    {
        $byLoad = fn (Appointment $appointment) => $eligible[$appointment->id]
            ->sortBy([
                fn (User $a, User $b) => ($load[$a->id.':'.$appointment->start_time->format('Y-m-d')] ?? 0)
                    <=> ($load[$b->id.':'.$appointment->start_time->format('Y-m-d')] ?? 0),
                fn (User $a, User $b) => strcmp((string) $a->first_name, (string) $b->first_name),
                fn (User $a, User $b) => $a->id <=> $b->id,
            ])
            ->values()
            ->all();

        $ordered = collect($group)
            ->sortBy([
                fn (Appointment $a, Appointment $b) => $eligible[$a->id]->count() <=> $eligible[$b->id]->count(),
                fn (Appointment $a, Appointment $b) => $a->start_time <=> $b->start_time,
                fn (Appointment $a, Appointment $b) => $a->id <=> $b->id,
            ])
            ->values()
            ->all();

        $options = [];
        foreach ($ordered as $appointment) {
            $options[$appointment->id] = $byLoad($appointment);
        }

        $total = count($ordered);
        $best = ['moved' => -1, 'assignment' => []];
        $nodes = 0;

        $search = function (int $index, array $assignment, int $moved) use (&$search, &$best, &$nodes, $ordered, $options, $total) {
            // Cannot beat the best already found, even if every rest moves.
            if ($moved + ($total - $index) <= $best['moved']) {
                return;
            }

            if ($index === $total) {
                $best = ['moved' => $moved, 'assignment' => $assignment];

                return;
            }

            if (++$nodes > self::SEARCH_NODE_LIMIT) {
                return;
            }

            $appointment = $ordered[$index];

            foreach ($options[$appointment->id] as $candidate) {
                $taken = collect($ordered)
                    ->take($index)
                    ->contains(fn (Appointment $other) => ($assignment[$other->id] ?? null)?->id === $candidate->id
                        && $other->start_time->lt($appointment->end_time)
                        && $other->end_time->gt($appointment->start_time));

                if ($taken) {
                    continue;
                }

                // Assigned, not spread: `[...$array]` renumbers integer keys,
                // and these keys are appointment ids.
                $next = $assignment;
                $next[$appointment->id] = $candidate;
                $search($index + 1, $next, $moved + 1);

                if ($best['moved'] === $total) {
                    return;
                }
            }

            $next = $assignment;
            $next[$appointment->id] = null;
            $search($index + 1, $next, $moved);
        };

        $search(0, [], 0);

        return $best['assignment'];
    }

    private function shiftCovers(ProviderScheduledWork $shift, Carbon $start, Carbon $end): bool
    {
        $date = $start->format('Y-m-d');

        return $start->gte(Carbon::parse($date.' '.$shift->start_time))
            && $end->lte(Carbon::parse($date.' '.$shift->end_time));
    }

    /** @return array{0: string, 1: string} the leave's first and last day, Y-m-d */
    private function range(ProviderTimeOff $leave): array
    {
        $from = Carbon::parse($leave->start_date)->format('Y-m-d');
        $to = Carbon::parse($leave->end_date ?? $leave->start_date)->format('Y-m-d');

        return [$from, $to];
    }

    /** @return array<string, mixed> */
    private function row(Appointment $appointment, ?User $target): array
    {
        $action = match (true) {
            $target !== null => self::ACTION_MOVE,
            in_array($appointment->payment_status, self::PAID_STATES, true) => self::ACTION_BLOCKED,
            default => self::ACTION_CANCEL,
        };

        return [
            'appointment_id' => $appointment->id,
            'number' => $appointment->number,
            'date' => $appointment->start_time->format('Y-m-d'),
            // For the plan tables: the dashboard and Filament show it as is.
            'date_label' => $appointment->start_time->format(DateFormat::date('Y-m-d')),
            'start_time' => $appointment->start_time->format('H:i'),
            'end_time' => $appointment->end_time->format('H:i'),
            'customer_id' => $appointment->customer_id,
            'customer_name' => $appointment->customer_name,
            'service_ids' => $appointment->services_record->pluck('service_id')->filter()->unique()->sort()->values()->all(),
            'services' => $appointment->services_record->sortBy('sequence_order')->pluck('service_name')->implode(', '),
            'action' => $action,
            'target_provider_id' => $target?->id,
            'target_provider_name' => $target?->full_name,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, signature: string, moves: int, cancellations: int, blocked: int}
     */
    private function summarise(ProviderTimeOff $leave, array $rows): array
    {
        $count = fn (string $action) => count(array_filter($rows, fn (array $row) => $row['action'] === $action));

        return [
            'rows' => $rows,
            'signature' => $rows === [] ? '' : $this->signature($leave, $rows),
            'moves' => $count(self::ACTION_MOVE),
            'cancellations' => $count(self::ACTION_CANCEL),
            'blocked' => $count(self::ACTION_BLOCKED),
        ];
    }

    /**
     * Fingerprint of exactly what the user is shown and agrees to: the leave,
     * and per booking its day, time, services, customer and fate. Any change to
     * one of them — not only to who takes a booking — voids a confirmation.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function signature(ProviderTimeOff $leave, array $rows): string
    {
        [$from, $to] = $this->range($leave);

        return hash('sha256', json_encode([
            'leave' => [
                'user_id' => (int) $leave->user_id,
                'type' => (int) $leave->type,
                'from' => $from,
                'to' => $to,
                'start_time' => $this->rawTime($leave, 'start_time'),
                'end_time' => $this->rawTime($leave, 'end_time'),
            ],
            'rows' => array_map(fn (array $row) => [
                $row['appointment_id'],
                $row['date'],
                $row['start_time'],
                $row['end_time'],
                $row['service_ids'],
                $row['customer_id'],
                $row['action'],
                $row['target_provider_id'],
            ], $rows),
        ]));
    }

    /**
     * start_time/end_time are cast to datetime, and reading an empty one back
     * through the cast yields "now" — so the raw column value is read instead.
     */
    private function rawTime(ProviderTimeOff $leave, string $column): ?string
    {
        $raw = $leave->getAttributes()[$column] ?? null;

        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('H:i');
        }

        if (blank($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw)->format('H:i');
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    private function rawDate(ProviderTimeOff $leave, string $column): ?string
    {
        $raw = $leave->getAttributes()[$column] ?? null;

        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        if (blank($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date !== false && $date->format('Y-m-d') === substr($value, 0, 10);
    }

    private static function isTime(string $value): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value);
    }
}
