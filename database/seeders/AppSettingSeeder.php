<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the user-facing application options.
 *
 * Currently the two appointment-reminder channel toggles. Push notifications stay
 * the always-on baseline; email and SMS are opt-in, so both default to `false`.
 * `validation` is stored on the row so the generic update route validates them.
 */
class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
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

        $this->command->info('App settings seeded successfully');
    }
}
