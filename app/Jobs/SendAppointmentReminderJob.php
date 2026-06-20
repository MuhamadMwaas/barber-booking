<?php

namespace App\Jobs;

use App\Enum\AppointmentStatus;
use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Services\UserSettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reminderId)
    {
    }

    public function handle(
        NotificationService $notificationService,
        UserSettingService $settings,
        SmsService $sms
    ): void {
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

        // The push notification + status flip are committed atomically. We capture
        // whether the reminder was actually sent so the opt-in email/SMS channels
        // only fire once (and never inside the DB transaction).
        $sent = DB::transaction(function () use ($reminder, $notificationService) {
            $reminder->refresh();
            if ($reminder->status !== 'pending' || $reminder->cancelled_at || $reminder->sent_at) {
                return false;
            }

            Log::info('Sending appointment reminder', [
                'reminder_id' => $reminder->id,
                'appointment_id' => $reminder->appointment_id,
                'user_id' => $reminder->user_id,
                'message_key' => $reminder->message_key,
                'params' => $reminder->params,
                'data' => $reminder->data,
            ]);

            $notificationService->sendNotificationToUser(
                $reminder->user,
                $reminder->title_key,
                $reminder->message_key,
                $reminder->params ?? [],
                $reminder->data ?? []
            );

            $reminder->markSent();

            return true;
        });

        if (!$sent) {
            return;
        }

        // Push is the always-on baseline. Email and SMS are opt-in extra channels:
        // we read the user's CURRENT preference here (at send time, not at schedule
        // time), so enabling/disabling a channel is reflected on the very next
        // reminder. A failure in one channel is logged and never blocks the others.
        $this->deliverOptInChannels($reminder, $notificationService, $settings, $sms);
    }

    /**
     * Send the email and/or SMS reminder if the recipient has opted in.
     */
    protected function deliverOptInChannels(
        AppointmentReminder $reminder,
        NotificationService $notificationService,
        UserSettingService $settings,
        SmsService $sms
    ): void {
        $user = $reminder->user;
        if (!$user) {
            return;
        }

        $locale = $reminder->locale ?? $user->locale ?? app()->getLocale();
        $params = $reminder->params ?? [];

        // Same translated text used by the push channel, resolved in the user's locale.
        $title = $notificationService->translateKey($reminder->title_key, $params, $locale);
        $message = $notificationService->translateKey($reminder->message_key, $params, $locale);

        // Email channel.
        if ($user->email && $settings->get($user, 'reminder_email_enabled')) {
            try {
                Mail::to($user->email)->send(
                    new AppointmentReminderMail($title, $message, $user->full_name)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send appointment reminder email', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // SMS channel (requires a phone number on the account).
        if ($user->phone && $settings->get($user, 'reminder_sms_enabled')) {
            try {
                $sms->send($user->phone, $message);
            } catch (\Throwable $e) {
                Log::error('Failed to send appointment reminder SMS', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
