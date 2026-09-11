<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the user-facing application options.
 *
 * The three appointment-reminder channel toggles. Every channel — push included —
 * is now a first-class option the customer controls, which is what makes "if the
 * customer enabled SMS only, only an SMS arrives" expressible at all.
 *
 * DEFAULTS ARE A MIGRATION DECISION, NOT A TASTE ONE. `reminder_push_enabled`
 * defaults to `true` because push used to be an unconditional baseline: seeding it
 * `false` would silently mute every reminder for every existing customer the
 * moment the gate goes live. Email and SMS keep their `false` default — they were
 * opt-in before and nobody consented to them retroactively (and SMS costs money
 * per message).
 *
 * `validation` is stored on the row so the generic update route validates them.
 */
class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'reminder_push_enabled',
                'label_translations' => [
                    'en' => 'Push appointment reminders',
                    'ar' => 'تذكير المواعيد عبر الإشعارات',
                    'de' => 'Terminerinnerungen per Push-Benachrichtigung',
                ],
                'description_translations' => [
                    'en' => 'Receive your appointment reminders as an in-app notification.',
                    'ar' => 'استقبل تذكيرات مواعيدك عبر إشعارات التطبيق.',
                    'de' => 'Erhalten Sie Ihre Terminerinnerungen als App-Benachrichtigung.',
                ],
                'type' => AppSetting::TYPE_BOOLEAN,
                'default_value' => true,
                'validation' => 'required|boolean',
                'group' => 'notifications',
                'sort_order' => 0,
            ],
            [
                'key' => 'reminder_email_enabled',
                'label_translations' => [
                    'en' => 'Email appointment reminders',
                    'ar' => 'تذكير المواعيد عبر الإيميل',
                    'de' => 'Terminerinnerungen per E-Mail',
                ],
                'description_translations' => [
                    'en' => 'Also receive your appointment reminders by email.',
                    'ar' => 'استقبل تذكيرات مواعيدك عبر البريد الإلكتروني أيضاً.',
                    'de' => 'Erhalten Sie Ihre Terminerinnerungen zusätzlich per E-Mail.',
                ],
                'type' => AppSetting::TYPE_BOOLEAN,
                'default_value' => false,
                'validation' => 'required|boolean',
                'group' => 'notifications',
                'sort_order' => 1,
            ],
            [
                'key' => 'reminder_sms_enabled',
                'label_translations' => [
                    'en' => 'SMS appointment reminders',
                    'ar' => 'تذكير المواعيد عبر الرسائل النصية',
                    'de' => 'Terminerinnerungen per SMS',
                ],
                'description_translations' => [
                    'en' => 'Also receive your appointment reminders by SMS.',
                    'ar' => 'استقبل تذكيرات مواعيدك عبر الرسائل النصية أيضاً.',
                    'de' => 'Erhalten Sie Ihre Terminerinnerungen zusätzlich per SMS.',
                ],
                'type' => AppSetting::TYPE_BOOLEAN,
                'default_value' => false,
                'validation' => 'required|boolean',
                'group' => 'notifications',
                'sort_order' => 2,
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        // Null-safe: the seeder is also run directly from tests, where there
        // is no console command attached.
        $this->command?->info('App settings seeded successfully');
    }
}
