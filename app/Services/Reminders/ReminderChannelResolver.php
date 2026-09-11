<?php

namespace App\Services\Reminders;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\UserSettingService;

/**
 * Decides which channels a customer's appointment reminder goes out on.
 *
 * THE RULE THIS CLASS EXISTS FOR: the delivery method follows the customer's
 * notification settings, with no privileged channel. If they enabled SMS only,
 * only an SMS is sent — no push, no email.
 *
 * Before this class, push was hard-wired as an "always-on baseline" straight in
 * SendAppointmentReminderJob while email and SMS were gated on settings. That
 * made the rule above impossible to express, and spread channel policy across a
 * job, a seeder and a settings service. All three channels are now one list, read
 * through one place.
 *
 * TWO DIFFERENT QUESTIONS, DELIBERATELY KEPT APART:
 *
 *  - {@see enabledChannels()} — "what did the customer ASK for?" This is the
 *    send-time gate. Push stays in even with no registered device, because
 *    NotificationService also writes the in-app notification record, which the
 *    customer sees next time they open the app.
 *
 *  - {@see resolve()} — "what will they ACTUALLY get?" This additionally checks
 *    whether the channel can reach them at all (SMS with no phone number on the
 *    account reaches nobody). The API returns this so the app can warn
 *    "you enabled SMS but haven't added a phone number" instead of promising a
 *    reminder that silently goes nowhere.
 *
 * Settings are read at SEND time, never at scheduling time, so a customer who
 * changes their mind after booking is honoured on the reminder they already set.
 */
class ReminderChannelResolver
{
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    /**
     * Channel → the `app_settings` key that switches it on.
     *
     * Adding a channel here plus a seeder row is the whole extension point; the
     * job, the API resource and the settings screen all follow from it.
     */
    public const SETTING_KEYS = [
        self::CHANNEL_PUSH => 'reminder_push_enabled',
        self::CHANNEL_EMAIL => 'reminder_email_enabled',
        self::CHANNEL_SMS => 'reminder_sms_enabled',
    ];

    public function __construct(
        protected UserSettingService $settings
    ) {
    }

    /**
     * Channels the customer has switched ON, regardless of whether we can reach
     * them there. This is the gate the reminder job uses.
     *
     * @return array<int, string>
     */
    public function enabledChannels(User $user): array
    {
        $enabled = [];

        foreach (self::SETTING_KEYS as $channel => $settingKey) {
            if ((bool) $this->settings->get($user, $settingKey)) {
                $enabled[] = $channel;
            }
        }

        return $enabled;
    }

    /**
     * Full per-channel picture for the API: switched on, reachable, and the
     * combination of the two.
     *
     * @return array<string, array{enabled: bool, deliverable: bool, effective: bool}>
     */
    public function resolve(User $user): array
    {
        $enabled = $this->enabledChannels($user);
        $result = [];

        foreach (array_keys(self::SETTING_KEYS) as $channel) {
            $isEnabled = in_array($channel, $enabled, true);
            $isDeliverable = $this->canReach($user, $channel);

            $result[$channel] = [
                'enabled' => $isEnabled,
                'deliverable' => $isDeliverable,
                'effective' => $isEnabled && $isDeliverable,
            ];
        }

        return $result;
    }

    /**
     * The channels that will genuinely reach this customer right now.
     *
     * @return array<int, string>
     */
    public function effectiveChannels(User $user): array
    {
        return array_keys(array_filter(
            $this->resolve($user),
            static fn (array $state): bool => $state['effective']
        ));
    }

    /**
     * Can this channel physically reach the user?
     *
     * Push needs a registered device; email and SMS need the corresponding
     * contact detail on the account. A channel that fails this is not an error —
     * it is something the app should tell the customer to fix.
     */
    public function canReach(User $user, string $channel): bool
    {
        return match ($channel) {
            self::CHANNEL_PUSH => UserDevice::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->exists(),
            self::CHANNEL_EMAIL => filled($user->email),
            self::CHANNEL_SMS => filled($user->phone),
            default => false,
        };
    }
}
