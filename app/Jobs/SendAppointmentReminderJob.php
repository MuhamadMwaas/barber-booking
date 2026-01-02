<?php

namespace App\Jobs;

use App\Enum\AppointmentStatus;
use App\Models\AppointmentReminder;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reminderId)
    {
    }

    public function handle(NotificationService $notificationService): void
    {
        $reminder = AppointmentReminder::with(['appointment', 'user'])
            ->find($this->reminderId);
                

        if (!$reminder) {
            return;
        }

        if ($reminder->status !== 'pending' || $reminder->cancelled_at || $reminder->sent_at) {
            return;
        }

        $appointment = $reminder->appointment;
        if (!$appointment) {
            $reminder->markCancelled();
            return;
        }

        $statusValue = $appointment->status?->value ?? $appointment->status;
        if ($appointment->cancelled_at || in_array($statusValue, AppointmentStatus::getCancelledStatuses(), true)) {
            $reminder->markCancelled();
            return;
        }

        // if ($reminder->remind_at->gt(now())) {
        //     return;
        // }

        DB::transaction(function () use ($reminder, $notificationService) {
            $reminder->refresh();
            if ($reminder->status !== 'pending' || $reminder->cancelled_at || $reminder->sent_at) {
                return;
            }

            Log::info('Sending appointment reminder', [
                'reminder_id' => $reminder->id,
                'appointment_id' => $reminder->appointment_id,
                'user_id' => $reminder->user_id,
                'message_key'=>$reminder->message_key,
                'params'=>$reminder->params,
                'data'=>$reminder->data,
            ]);

            $notificationService->sendNotificationToUser(
                $reminder->user,
                $reminder->title_key,
                $reminder->message_key,
                $reminder->params ?? [],
                $reminder->data ?? []
            );

            $reminder->markSent();
        });
    }
}
