<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReasonLeave;

class ReasonLeaveSeeder extends Seeder
{
    public function run(): void
    {
      $reasons = [
            [
                'default' => [
                    'name' => 'Annual Leave',
                    'description' => 'Paid annual vacation leave',
                ],
                'translations' => [
                    'en' => ['name' => 'Annual Leave', 'description' => 'Paid annual vacation leave'],
                    'de' => ['name' => 'Jahresurlaub', 'description' => 'Bezahlter Jahresurlaub'],
                    'ar' => ['name' => 'إجازة سنوية', 'description' => 'إجازة سنوية مدفوعة'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Sick Leave',
                    'description' => 'Medical sick leave with certificate',
                ],
                'translations' => [
                    'en' => ['name' => 'Sick Leave', 'description' => 'Medical sick leave with certificate'],
                    'de' => ['name' => 'Krankenstand', 'description' => 'Krankheit mit ärztlichem Attest'],
                    'ar' => ['name' => 'إجازة مرضية', 'description' => 'إجازة مرضية مع شهادة طبية'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Emergency Leave',
                    'description' => 'Urgent personal or family emergency',
                ],
                'translations' => [
                    'en' => ['name' => 'Emergency Leave', 'description' => 'Urgent personal or family emergency'],
                    'de' => ['name' => 'Notfallurlaub', 'description' => 'Dringende persönliche oder familiäre Notfälle'],
                    'ar' => ['name' => 'إجازة طارئة', 'description' => 'حالة طارئة شخصية أو عائلية'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Maternity Leave',
                    'description' => 'Maternity leave for expecting mothers',
                ],
                'translations' => [
                    'en' => ['name' => 'Maternity Leave', 'description' => 'Maternity leave for expecting mothers'],
                    'de' => ['name' => 'Mutterschaftsurlaub', 'description' => 'Mutterschutz für werdende Mütter'],
                    'ar' => ['name' => 'إجازة أمومة', 'description' => 'إجازة أمومة للحوامل'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Paternity Leave',
                    'description' => 'Paternity leave for new fathers',
                ],
                'translations' => [
                    'en' => ['name' => 'Paternity Leave', 'description' => 'Paternity leave for new fathers'],
                    'de' => ['name' => 'Vaterschaftsurlaub', 'description' => 'Vaterschaftsurlaub für neue Väter'],
                    'ar' => ['name' => 'إجازة أبوة', 'description' => 'إجازة أبوة للآباء الجدد'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Bereavement Leave',
                    'description' => 'Leave due to death in family',
                ],
                'translations' => [
                    'en' => ['name' => 'Bereavement Leave', 'description' => 'Leave due to death in family'],
                    'de' => ['name' => 'Trauerurlaub', 'description' => 'Freistellung bei Todesfall in der Familie'],
                    'ar' => ['name' => 'إجازة عزاء', 'description' => 'إجازة بسبب وفاة في الأسرة'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Training/Conference',
                    'description' => 'Professional development and training',
                ],
                'translations' => [
                    'en' => ['name' => 'Training/Conference', 'description' => 'Professional development and training'],
                    'de' => ['name' => 'Schulung/Konferenz', 'description' => 'Berufliche Weiterbildung und Schulung'],
                    'ar' => ['name' => 'تدريب/مؤتمر', 'description' => 'تطوير مهني وتدريب'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Religious Holiday',
                    'description' => 'Religious observance days',
                ],
                'translations' => [
                    'en' => ['name' => 'Religious Holiday', 'description' => 'Religious observance days'],
                    'de' => ['name' => 'Religiöser Feiertag', 'description' => 'Tage religiöser Feiertage'],
                    'ar' => ['name' => 'عطلة دينية', 'description' => 'أيام الاحتفال الديني'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Personal Day',
                    'description' => 'Personal matters requiring time off',
                ],
                'translations' => [
                    'en' => ['name' => 'Personal Day', 'description' => 'Personal matters requiring time off'],
                    'de' => ['name' => 'Persönlicher Tag', 'description' => 'Persönliche Angelegenheiten, die eine Freistellung erfordern'],
                    'ar' => ['name' => 'يوم شخصي', 'description' => 'أمور شخصية تتطلب إجازة'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Medical Appointment',
                    'description' => 'Scheduled medical appointments',
                ],
                'translations' => [
                    'en' => ['name' => 'Medical Appointment', 'description' => 'Scheduled medical appointments'],
                    'de' => ['name' => 'Arzttermin', 'description' => 'Geplante Arzttermine'],
                    'ar' => ['name' => 'موعد طبي', 'description' => 'مواعيد طبية مجدولة'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Unpaid Leave',
                    'description' => 'Unpaid time off by request',
                ],
                'translations' => [
                    'en' => ['name' => 'Unpaid Leave', 'description' => 'Unpaid time off by request'],
                    'de' => ['name' => 'Unbezahlter Urlaub', 'description' => 'Unbezahlte Freistellung auf Anfrage'],
                    'ar' => ['name' => 'إجازة بدون أجر', 'description' => 'إجازة بدون أجر بناءً على طلب'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Compensatory Time Off',
                    'description' => 'Time off to compensate for overtime work',
                ],
                'translations' => [
                    'en' => ['name' => 'Compensatory Time Off', 'description' => 'Time off to compensate for overtime work'],
                    'de' => ['name' => 'Ausgleichsfreizeit', 'description' => 'Freizeit als Ausgleich für Überstunden'],
                    'ar' => ['name' => 'تعويض وقت العمل', 'description' => 'إجازة كتعويض عن العمل الإضافي'],
                ],
            ],
        ];

        foreach ($reasons as $reasonData) {

            $reasonLeave = ReasonLeave::create([
                'name' => $reasonData['default']['name'],
                'description' => $reasonData['default']['description'],
            ]);

            foreach ($reasonData['translations'] as $locale => $attrs) {
                $reasonLeave->translate($locale, [
                    'language_code' => $locale,
                    'name' => $attrs['name'],
                    'description' => $attrs['description'],
                ]);
            }
        }

        $this->command->info('Leave reasons seeded with translations (en de ar)');

    }
}
