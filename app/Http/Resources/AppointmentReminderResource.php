<?php

namespace App\Http\Resources;

use App\Services\Reminders\ReminderChannelResolver;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One appointment reminder, shaped for the mobile app's reminder control.
 *
 * `offset_hours` is the field the app actually renders: it is what fills the
 * "1 hour before / 2 hours before / …" dropdown when the customer reopens a
 * booking. It is null only for a legacy reminder scheduled through the old
 * free-form `remind_at` at a time that does not land on a whole hour — in which
 * case the app should show `remind_at` and leave the dropdown unselected.
 *
 * `channels` answers "will this actually reach me?", not just "what did I switch
 * on?". A customer who enabled SMS but never added a phone number sees
 * `enabled: true, deliverable: false`, and the app can say so instead of
 * promising a reminder that goes nowhere.
 */
class AppointmentReminderResource extends JsonResource
{
    public function toArray($request): array
    {
        $channels = $this->resolveChannels();

        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'remind_at' => $this->remind_at?->toIso8601String(),
            'offset_hours' => $this->offsetHours(),
            'status' => $this->status,
            'is_active' => $this->active_slot !== null,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),

            // Channels the reminder actually went out on. Null until it fires;
            // an empty array afterwards means every channel was switched off.
            'delivered_channels' => $this->delivered_channels,

            'channels' => $channels,
            'active_channels' => array_keys(array_filter(
                $channels,
                static fn (array $state): bool => $state['effective']
            )),
            // The app shows a warning when this is false: the reminder is saved,
            // but as things stand nothing will reach the customer.
            'has_active_channel' => collect($channels)->contains(
                static fn (array $state): bool => $state['effective']
            ),
        ];
    }

    /**
     * @return array<string, array{enabled: bool, deliverable: bool, effective: bool}>
     */
    protected function resolveChannels(): array
    {
        $user = $this->user ?? $this->appointment?->customer;

        if (! $user) {
            return [];
        }

        return app(ReminderChannelResolver::class)->resolve($user);
    }
}
