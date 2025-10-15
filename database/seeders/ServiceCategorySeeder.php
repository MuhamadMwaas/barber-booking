<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'default' => [
                    'name' => 'Haircut',
                    'description' => 'Standard haircut services',
                ],
                'translations' => [
                    'en' => ['name' => 'Haircut', 'description' => 'Standard haircut services'],
                    'de' => ['name' => 'Haarschnitt', 'description' => 'Standard Haarschnitt Dienstleistungen'],
                    'ar' => ['name' => 'قص الشعر', 'description' => 'خدمات قص الشعر القياسية'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Beard Trim',
                    'description' => 'Beard trimming and shaping',
                ],
                'translations' => [
                    'en' => ['name' => 'Beard Trim', 'description' => 'Beard trimming and shaping'],
                    'de' => ['name' => 'Bart schneiden', 'description' => 'Bart trimmen und formen'],
                    'ar' => ['name' => 'تقليم اللحية', 'description' => 'تقليم وتشكيل اللحية'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Shave',
                    'description' => 'Wet shave and straight razor service',
                ],
                'translations' => [
                    'en' => ['name' => 'Shave', 'description' => 'Wet shave and straight razor service'],
                    'de' => ['name' => 'Rasur', 'description' => 'Nassrasur und Rasiermesser-Service'],
                    'ar' => ['name' => 'حلاقة', 'description' => 'حلاقة مبللة وحلاقة بالشفرة'],
                ],
            ],
            [
                'default' => [
                    'name' => 'Coloring',
                    'description' => 'Hair coloring and highlights',
                ],
                'translations' => [
                    'en' => ['name' => 'Coloring', 'description' => 'Hair coloring and highlights'],
                    'de' => ['name' => 'Färbung', 'description' => 'Haarfärbung und Strähnchen'],
                    'ar' => ['name' => 'صبغ الشعر', 'description' => 'صبغ الشعر والهايلايت'],
                ],
            ],
        ];

        $order = 1;
        foreach ($categories as $cat) {

            $category = ServiceCategory::updateOrCreate(
                ['name' => $cat['default']['name']],
                [
                    'description' => $cat['default']['description'],
                    'is_active' => true,
                    'sort_order' => $order,
                ]
            );


            foreach ($cat['translations'] as $locale => $attrs) {
                $category->translate($locale, [
                    'language_code' => $locale,
                    'name' => $attrs['name'],
                    'description' => $attrs['description'],
                ]);
            }

            $order++;
        }

        $this->command->info('Service categories seeded with translations (en de ar)');
    }
}
