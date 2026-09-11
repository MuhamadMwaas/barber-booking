<?php

namespace App\Jobs;

use App\Enum\AppointmentStatus;
use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Services\NotificationService;
use App\Services\Reminders\ReminderChannelResolver;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Deliver one appointment reminder on whichever channels the customer chose.
 *
 * THE CONTRACT: no channel is privileged. Push, email and SMS are each gated on
 * the customer's own setting, so "enabled SMS only → only an SMS arrives" holds
 * exactly. Push used to be sent unconditionally before the gate existed, which
 * made that promise impossible to keep.
 *
 * THE ORDER OF OPERATIONS MATTERS:
 *
 *   1. CLAIM the reminder inside a transaction — lock the row, re-check it is
 *      still pending, mark it sent. Nothing is delivered yet.
 *   2. DELIVER outside the transaction, one channel at a time.
 *   3. RECORD which channels actually went out.
 *
 * Claiming first is what makes a retried or duplicated job safe: the second
 * runner finds the row already `sent` and stops before sending anything. The
 * alternative — deliver, then mark — sends twice whenever the mark fails.
 *
 * Delivery is deliberately OUTSIDE the transaction. Push, mail and SMS are all
 * network calls; holding a row lock open across them would keep a database
 * transaction alive for the length of an SMTP handshake, and a rollback could
 * never un-send a message that already left. This also fixes the older coupling
 * where push lived inside the claiming transaction: gating push meant the
 * transaction returned "nothing sent", and email and SMS were skipped along with
 * it — so switching push off would have silently switched off every channel.
 */
class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reminderId)
    {
    }

    public function handle(
        NotificationService $notificationService,
        ReminderChannelResolver $channels,
        SmsService $sms
    ): void {
        $reminder = AppointmentReminder::with(['appointment', 'user'])
            ->find($this->reminderId);

        if (! $reminder) {
            return;
        }

        if ($reminder->status !== AppointmentReminder::STATUS_PENDING || $reminder->cancelled_at || $reminder->sent_at) {
            return;
        }

        $appointment = $reminder->appointment;
        if (! $appointment) {
            $reminder->markCancelled();
            return;
        }

        $statusValue = $appointment->status?->value ?? $appointment->status;
        if ($appointment->cancelled_at || in_array($statusValue, AppointmentStatus::getCancelledStatuses(), true)) {
            $reminder->markCancelled();
            return;
        }

        $user = $reminder->user;
        if (! $user) {
            $reminder->markCancelled();
            return;
        }

        // ── 1. Claim ────────────────────────────────────────────────────────
        if (! $this->claim($reminder)) {
            return;
        }

        // ── 2. Deliver ──────────────────────────────────────────────────────
        // Settings are read HERE, at send time, not when the reminder was
        // scheduled: a customer who switches SMS on the morning of their
        // appointment gets an SMS for a reminder booked a week earlier.
        $enabled = $channels->enabledChannels($user);

        Log::info('Delivering appointment reminder', [
            'reminder_id' => $reminder->id,
            'appointment_id' => $reminder->appointment_id,
            'user_id' => $user->id,
            'enabled_channels' => $enabled,
        ]);

        $delivered = $this->deliver($reminder, $enabled, $notificationService, $sms);

        // ── 3. Record ───────────────────────────────────────────────────────
        // An EMPTY list is a legitimate outcome, not a failure: the customer had
        // every channel switched off by the time the reminder came due. Storing
        // it is the difference between diagnosing "I got no reminder" and
        // guessing at it.
        $reminder->recordDeliveredChannels($delivered);

        if ($delivered === []) {
            Log::warning('Appointment reminder had no deliverable channel', [
                'reminder_id' => $reminder->id,
                'user_id' => $user->id,
                'enabled_channels' => $enabled,
            ]);
        }
    }

    /**
     * Take exclusive ownership of the reminder, or report that someone else has.
     *
     * The row is locked and re-read INSIDE the transaction. A status check made
     * before the lock is a fast rejection, never a guarantee — the same rule the
     * booking and invoice-numbering paths follow.
     */
    protected function claim(AppointmentReminder $reminder): bool
    {
        return (bool) DB::transaction(function () use ($reminder) {
            $locked = AppointmentReminder::whereKey($reminder->id)
                ->lockForUpdate()
                ->first();

            if (! $locked
                || $locked->status !== AppointmentReminder::STATUS_PENDING
                || $locked->cancelled_at
                || $locked->sent_at
            ) {
                return false;
            }

            $reminder->markSent();

            return true;
        });
    }

    /**
     * Attempt every enabled channel and report the ones that succeeded.
     *
     * Each channel is isolated in its own try/catch: a bounced email must not
     * cost the customer their SMS. A channel the customer enabled but cannot be
     * reached on (SMS with no phone number) is skipped and logged, not treated
     * as an error — there is nothing to retry.
     *
     * @param  array<int, string>  $enabled
     * @return array<int, string>
     */
    protected function deliver(
        AppointmentReminder $reminder,
        array $enabled,
        NotificationService $notificationService,
        SmsService $sms
    ): array {
        $user = $reminder->user;
        $locale = $reminder->locale ?? $user->locale ?? app()->getLocale();
        $params = $reminder->params ?? [];
        $delivered = [];

        // Resolved once and shared, so the three channels can never show the
        // customer three differently-worded versions of the same reminder.
        $title = $notificationService->translateKey($reminder->title_key, $params, $locale);
        $message = $notificationService->translateKey($reminder->message_key, $params, $locale);

        // Push / in-app notification.
        if (in_array(ReminderChannelResolver::CHANNEL_PUSH, $enabled, true)) {
            try {
                $notificationService->sendNotificationToUser(
                    $user,
                    $reminder->title_key,
                    $reminder->message_key,
                    $params,
                    $reminder->data ?? []
                );
                $delivered[] = ReminderChannelResolver::CHANNEL_PUSH;
            } catch (\Throwable $e) {
                Log::error('Failed to send appointment reminder push', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Email.
        if (in_array(ReminderChannelResolver::CHANNEL_EMAIL, $enabled, true)) {
            if (blank($user->email)) {
                Log::info('Appointment reminder email skipped: no address on the account', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $user->id,
                ]);
            } else {
                try {
                    Mail::to($user->email)->send(
                        new AppointmentReminderMail($title, $message, $user->full_name)
                    );
                    $delivered[] = ReminderChannelResolver::CHANNEL_EMAIL;
                } catch (\Throwable $e) {
                    Log::error('Failed to send appointment reminder email', [
                        'reminder_id' => $reminder->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // SMS.
        if (in_array(ReminderChannelResolver::CHANNEL_SMS, $enabled, true)) {
            if (blank($user->phone)) {
                Log::info('Appointment reminder SMS skipped: no phone number on the account', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $user->id,
                ]);
            } else {
                try {
                    $sms->send($user->phone, $message);
                    $delivered[] = ReminderChannelResolver::CHANNEL_SMS;
                } catch (\Throwable $e) {
                    Log::error('Failed to send appointment reminder SMS', [
                        'reminder_id' => $reminder->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $delivered;
    }
}
