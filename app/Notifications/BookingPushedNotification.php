<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * BookingPushedNotification
 *
 * Sent to a registered customer (with customer_id) when their existing booking
 * has been rescheduled (pushed forward) due to a staff-side service addition
 * on an earlier booking with the same provider.
 *
 * Channels:
 *   - database (always)
 *
 * Push delivery (OneSignal) is handled in parallel by
 * PushBookingsService::executePushPlan() through NotificationService,
 * mirroring the existing project conventions.
 */
class BookingPushedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
        public int $pushedMinutes
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Database representation — matches the shape used by PhoneNotification
     * so the existing notifications UI can render it without changes.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title_key' => 'notification.booking_pushed_title',
            'message_key' => 'notification.booking_pushed_body',
            'data' => [
                'type' => 'booking_pushed',
                'appointment_id' => $this->appointment->id,
                'appointment_number' => $this->appointment->number,
                'original_start_time' => $this->appointment->original_start_time?->format('H:i'),
                'new_start_time' => $this->appointment->start_time?->format('H:i'),
                'pushed_minutes' => $this->pushedMinutes,
            ],
            'params' => [
                'number' => $this->appointment->number,
                'time' => $this->appointment->start_time?->format('H:i'),
                'minutes' => $this->pushedMinutes,
            ],
        ];
    }
}
