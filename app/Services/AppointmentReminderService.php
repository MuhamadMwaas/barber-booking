<?php

namespace App\Services;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AppointmentReminderService
{
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
            'params' => $params,
            'data' => $data,
        ]);

        return DB::transaction(function () use ($appointment, $customer, $remindAt, $params, $data, $titleKey, $messageKey) {



            $reminder = AppointmentReminder::create([
                'appointment_id' => $appointment->id,
                'user_id' => $customer->id,
                'remind_at' => $remindAt,
                'status' => 'pending',
                'locale' => $customer->locale ?? app()->getLocale(),
                'title_key' =>  $titleKey ,
                'message_key' =>  $messageKey,
                'params' => $params,
                'data' => $data,
            ]);

            SendAppointmentReminderJob::dispatch($reminder->id)
                ->delay($remindAt);

            return $reminder;
        });
    }

    public function rescheduleReminder(
        Appointment $appointment,
        Carbon $remindAt,
        array $params = [],
        array $data = [],
        $titleKey = 'appointment_reminder.title',
        $messageKey = 'appointment_reminder.message'
    ): AppointmentReminder {
        $this->cancelRemindersForAppointment($appointment);
        return $this->scheduleReminder($appointment, $remindAt, $params, $data, $titleKey, $messageKey);
    }

    public function cancelRemindersForAppointment(Appointment $appointment): int
    {
        return AppointmentReminder::where('appointment_id', $appointment->id)
            ->where('status', 'pending')
            ->whereNull('cancelled_at')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
    }
}
