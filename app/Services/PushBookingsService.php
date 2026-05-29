<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\BookingPushedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PushBookingsService
 *
 * Two responsibilities:
 *   1) planPushFrom()    — pure planning: produce a "what would be pushed"
 *      list without touching the database.
 *   2) executePushPlan() — mutate the database according to a previously-
 *      produced plan (called inside an existing DB transaction).
 *
 * Algorithm: CASCADING CONDITIONAL.
 *   For each appointment in order of start_time:
 *     - If it overlaps with the current "fixed boundary" → push it
 *       just enough to clear the boundary.
 *     - If it doesn't overlap → STOP (no more pushes needed downstream).
 *
 * Guards:
 *   - PAID appointments NEVER get pushed → return is_possible=false.
 *   - Pushing past provider's work end → return is_possible=false.
 */
class PushBookingsService
{
    /**
     * Plan a cascading push.
     *
     * @param User        $provider         The provider whose bookings may be pushed.
     * @param Carbon      $fixedUntil       Time until which space must be clear
     *                                       (i.e. new service's end_time).
     * @param Appointment $firstCandidate   First downstream appointment to evaluate.
     * @param int         $excludeAnchorId  ID of the anchor appointment (must not be touched).
     *
     * @return array{
     *   is_possible: bool,
     *   reason: ?string,
     *   blocking_appointment_number: ?string,
     *   plan: array
     * }
     */
    public function planPushFrom(
        User $provider,
        Carbon $fixedUntil,
        Appointment $firstCandidate,
        int $excludeAnchorId
    ): array {
        $date = $firstCandidate->appointment_date->format('Y-m-d');

        // Provider work-end for the day (used as upper bound)
        $schedule = DB::table('provider_scheduled_works')
            ->where('user_id', $provider->id)
            ->where('day_of_week', Carbon::parse($date)->dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();

        $workEnd = $schedule
            ? Carbon::parse($date . ' ' . $schedule->end_time)
            : Carbon::parse($date . ' 23:59');

        // All candidates: same provider, same day, after firstCandidate.start_time (inclusive),
        // not cancelled / completed, ordered by start_time.
        $candidates = Appointment::query()
            ->where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
                AppointmentStatus::COMPLETED->value,
            ])
            ->where('id', '!=', $excludeAnchorId)
            ->where('start_time', '>=', $firstCandidate->start_time)
            ->orderBy('start_time', 'asc')
            ->get();

        $plan = [];
        $currentBoundary = $fixedUntil->copy();

        foreach ($candidates as $appt) {
            // Stop chain: this candidate is already past the boundary.
            if ($appt->start_time->gte($currentBoundary)) {
                break;
            }

            // BLOCKER: paid appointments cannot be pushed.
            if (in_array($appt->payment_status->value, [
                PaymentStatus::PAID_ONLINE->value,
                PaymentStatus::PAID_ONSTIE_CASH->value,
                PaymentStatus::PAID_ONSTIE_CARD->value,
            ], true)) {
                return [
                    'is_possible' => false,
                    'reason' => 'paid_booking_in_chain',
                    'blocking_appointment_number' => $appt->number,
                    'plan' => [],
                ];
            }

            // Compute new times for this appointment.
            $pushMinutes = (int) $appt->start_time->diffInMinutes($currentBoundary);
            $newStart    = $currentBoundary->copy();
            $newEnd      = $newStart->copy()->addMinutes($appt->duration_minutes);

            // Work-hours bound.
            if ($newEnd->gt($workEnd)) {
                return [
                    'is_possible' => false,
                    'reason' => 'exceeds_work_hours',
                    'blocking_appointment_number' => $appt->number,
                    'plan' => [],
                ];
            }

            $plan[] = [
                'appointment_id' => $appt->id,
                'appointment_number' => $appt->number,
                'customer_name' => $appt->customer
                    ? $appt->customer->full_name
                    : ($appt->getRawOriginal('customer_name') ?: 'Guest'),
                'has_customer_account' => (bool) $appt->customer_id,
                'original_start' => $appt->start_time->format('H:i'),
                'original_end' => $appt->end_time->format('H:i'),
                'new_start' => $newStart->format('H:i'),
                'new_end' => $newEnd->format('H:i'),
                'push_minutes' => $pushMinutes,
            ];

            // Advance boundary for the next iteration.
            $currentBoundary = $newEnd->copy();
        }

        return [
            'is_possible' => true,
            'reason' => null,
            'blocking_appointment_number' => null,
            'plan' => $plan,
        ];
    }

    /**
     * Execute a previously-produced plan. MUST be called inside a DB transaction
     * managed by the caller (so a downstream failure rolls everything back).
     *
     * Behavior per appointment:
     *  - Update start_time, end_time.
     *  - Save original_start_time / original_end_time ONLY on the first push
     *    (preserve the truest original — subsequent pushes leave them alone).
     *  - Mark was_pushed=true, last_pushed_at=now().
     *  - Dispatch BookingPushedNotification to registered customers (guests are
     *    skipped — no channel). Notification failures are swallowed so they
     *    can never break the transaction.
     *
     * @return int[] IDs of the pushed appointments.
     */
    public function executePushPlan(array $plan, string $date): array
    {
        $pushedIds = [];

        foreach ($plan as $item) {
            /** @var Appointment|null $appt */
            $appt = Appointment::find($item['appointment_id']);
            if (! $appt) {
                continue;
            }

            $newStart = Carbon::parse($date . ' ' . $item['new_start']);
            $newEnd   = Carbon::parse($date . ' ' . $item['new_end']);

            $updates = [
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'last_pushed_at' => now(),
                'was_pushed' => true,
            ];

            // Preserve original ONLY on first push.
            if (! $appt->was_pushed) {
                $updates['original_start_time'] = $appt->start_time;
                $updates['original_end_time'] = $appt->end_time;
            }

            $appt->update($updates);
            $pushedIds[] = $appt->id;

            // Notify registered customers (non-blocking — never let it fail the txn).
            if ($appt->customer_id && $appt->customer) {
                try {
                    $fresh = $appt->fresh();
                    // 1) DB notification (in-app history)
                    $appt->customer->notify(
                        new BookingPushedNotification($fresh, (int) $item['push_minutes'])
                    );
                    // 2) Push notification via OneSignal — uses the existing
                    //    NotificationService so translations + device routing
                    //    follow project conventions.
                    /** @var \App\Services\NotificationService $notifier */
                    $notifier = app(NotificationService::class);
                    $notifier->sendNotificationToUser(
                        $appt->customer,
                        'notification.booking_pushed_title',
                        'notification.booking_pushed_body',
                        [
                            'number' => $appt->number,
                            'time' => $fresh->start_time?->format('H:i'),
                            'minutes' => (int) $item['push_minutes'],
                        ],
                        [
                            'type' => 'booking_pushed',
                            'appointment_id' => $appt->id,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('BookingPushedNotification failed', [
                        'appointment_id' => $appt->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $pushedIds;
    }
}
