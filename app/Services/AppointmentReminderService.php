<?php

namespace App\Services;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Scheduling, rescheduling and cancelling appointment reminders.
 *
 * One appointment carries at most ONE live reminder (see
 * {@see AppointmentReminder::ACTIVE_SLOT}). Changing the lead time is therefore a
 * cancel-then-create, not an update: the old row survives as history so support
 * can reconstruct what the customer actually chose and when.
 *
 * WHICH channels the reminder goes out on is NOT decided here — that is read at
 * send time by {@see \App\Services\Reminders\ReminderChannelResolver}, so a
 * customer who changes their notification settings after booking is honoured on
 * the reminder they already scheduled.
 */
class AppointmentReminderService
{
    /**
     * Turn a lead time in hours into the absolute moment the reminder fires.
     *
     * WHY THE API PREFERS AN OFFSET OVER A TIMESTAMP: the app's dropdown offers
     * lead times ("1 hour before"), not instants, and a client-computed instant
     * has to survive a timezone round-trip to mean the same thing on the server.
     * `APP_TIMEZONE` is Asia/Baghdad while the salon runs on Berlin hours, so a
     * client that posts a naive `2026-08-29 09:00:00` and a client that posts
     * `2026-08-29T09:00:00Z` ask for two different moments hours apart. Deriving
     * the instant here — from the appointment's own start_time, in the server's
     * own timezone — removes the ambiguity entirely instead of documenting it.
     */
    public function remindAtFromOffset(Appointment $appointment, int $offsetHours): Carbon
    {
        if (! in_array($offsetHours, $this->allowedOffsetHours(), true)) {
            throw new InvalidArgumentException(
                __('main.appointment.validation.offset_hours.in')
            );
        }

        return $appointment->start_time->copy()->subHours($offsetHours);
    }

    /**
     * Lead times the customer may choose, in hours.
     *
     * @return array<int, int>
     */
    public function allowedOffsetHours(): array
    {
        return array_values(array_map(
            'intval',
            config('appointment_reminders.offset_hours', [1, 2, 3, 4, 5, 6, 24])
        ));
    }

    /**
     * The appointment's live reminder, if it has one.
     */
    public function activeReminderFor(Appointment $appointment): ?AppointmentReminder
    {
        return AppointmentReminder::query()
            ->where('appointment_id', $appointment->id)
            ->active()
            ->latest('id')
            ->first();
    }

    public function scheduleReminder(
        Appointment $appointment,
        Carbon $remindAt,
        array $params = [],
        array $data = [],
        $titleKey = 'appointment_reminder.title',
        $messageKey = 'appointment_reminder.message'
    ): AppointmentReminder {
        if ($remindAt->lte(now())) {
            throw new InvalidArgumentException('Remind_at must be in the future.');
        }

        if ($remindAt->gte($appointment->start_time)) {
            throw new InvalidArgumentException('Remind_at must be before appointment start time.');
        }

        $customer = $appointment->customer;
        if (!$customer) {
            throw new InvalidArgumentException('Appointment has no customer to notify.');
        }

        Log::info('Scheduling appointment reminder', [
            'appointment_id' => $appointment->id,
            'user_id' => $customer->id,
            'remind_at' => $remindAt->toDateTimeString(),
            'title_key' => $titleKey,
            'message_key' => $messageKey,
        ]);

        return DB::transaction(function () use ($appointment, $customer, $remindAt, $params, $data, $titleKey, $messageKey) {
            $reminder = AppointmentReminder::create([
                'appointment_id' => $appointment->id,
                'user_id' => $customer->id,
                'remind_at' => $remindAt,
                'status' => AppointmentReminder::STATUS_PENDING,
                // Claims the one live slot. The unique index refuses a second.
                'active_slot' => AppointmentReminder::ACTIVE_SLOT,
                'locale' => $customer->locale ?? app()->getLocale(),
                'title_key' => $titleKey,
                'message_key' => $messageKey,
                'params' => $params,
                'data' => $data,
            ]);

            SendAppointmentReminderJob::dispatch($reminder->id)
                ->delay($remindAt);

            return $reminder;
        });
    }

    /**
     * Replace whatever reminder the appointment has with a new one.
     *
     * Cancel-then-create inside ONE transaction: the old row must give up its
     * live slot before the new row can claim it, and a failure halfway through
     * must not leave the appointment with no reminder at all.
     */
    public function rescheduleReminder(
        Appointment $appointment,
        Carbon $remindAt,
        array $params = [],
        array $data = [],
        $titleKey = 'appointment_reminder.title',
        $messageKey = 'appointment_reminder.message'
    ): AppointmentReminder {
        return DB::transaction(function () use ($appointment, $remindAt, $params, $data, $titleKey, $messageKey) {
            $this->cancelRemindersForAppointment($appointment);

            return $this->scheduleReminder($appointment, $remindAt, $params, $data, $titleKey, $messageKey);
        });
    }

    /**
     * Retire every live reminder on the appointment.
     *
     * `active_slot => null` is the load-bearing half, not the status: it is the
     * column the unique index is built on, so it is what frees the slot for the
     * next reminder. Writing `status` alone would leave the row occupying the
     * slot and the very next schedule would hit a duplicate-key error.
     *
     * Scoped by `active_slot` rather than by status for the same reason — the
     * query and the constraint read the same column, so they cannot disagree.
     */
    public function cancelRemindersForAppointment(Appointment $appointment): int
    {
        return AppointmentReminder::query()
            ->where('appointment_id', $appointment->id)
            ->active()
            ->update([
                'status' => AppointmentReminder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'active_slot' => null,
            ]);
    }

    /**
     * Standard notification payload for an appointment reminder.
     *
     * Lives here so the two callers that schedule reminders — the dedicated
     * endpoint and the booking endpoint — cannot drift into building slightly
     * different payloads for the same notification.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function buildPayload(Appointment $appointment): array
    {
        $params = [
            'date' => [
                'type' => 'value',
                'value' => $appointment->start_time->format('Y-m-d'),
            ],
            'time' => [
                'type' => 'value',
                'value' => $appointment->start_time->format('H:i'),
            ],
            'number' => [
                'type' => 'value',
                'value' => $appointment->number,
            ],
        ];

        $data = [
            'type' => 'appointment_reminder',
            'appointment_id' => $appointment->id,
        ];

        return [$params, $data];
    }
}
