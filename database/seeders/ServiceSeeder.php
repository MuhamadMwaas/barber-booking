<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Laravolt\Avatar\Facade as Avatar;
class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $categories = ServiceCategory::all();

        $services = [
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Men\'s Haircut',
                    'description' => 'Classic barber haircut with wash and light styling',
                ],
                'translations' => [
                    'en' => ['name' => 'Men\'s Haircut', 'description' => 'Classic barber haircut with wash and light styling'],
                    'de' => ['name' => 'Herrenhaarschnitt', 'description' => 'Klassischer Barbershop-Haarschnitt mit Waschen und leichtem Styling'],
                    'ar' => ['name' => 'قص شعر رجالي', 'description' => 'قص شعر كلاسيكي عند الحلاق مع غسيل وتصفيف خفيف'],
                ],
                'price' => 28.00,
                'discount_price' => null,
                'duration_minutes' => 35,
                'color_code' => '#FF6B9D',
                'is_featured' => true,
            ],
            [
                'category' => 'Beard Trim',
                'default' => [
                    'name' => 'Beard Trim & Shape',
                    'description' => 'Detailed beard trimming with line-up and shaping',
                ],
                'translations' => [
                    'en' => ['name' => 'Beard Trim & Shape', 'description' => 'Detailed beard trimming with line-up and shaping'],
                    'de' => ['name' => 'Bart trimmen & formen', 'description' => 'Präzises Barttrimmen mit Konturen und Formgebung'],
                    'ar' => ['name' => 'تقليم وتهذيب اللحية', 'description' => 'تقليم دقيق للحية مع تحديد وتشذيب الأطراف'],
                ],
                'price' => 18.00,
                'discount_price' => null,
                'duration_minutes' => 20,
                'color_code' => '#C724B1',
                'is_featured' => true,
            ],
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Skin Fade Haircut',
                    'description' => 'Modern skin fade with precise blending and styling',
                ],
                'translations' => [
                    'en' => ['name' => 'Skin Fade Haircut', 'description' => 'Modern skin fade with precise blending and styling'],
                    'de' => ['name' => 'Skin-Fade-Haarschnitt', 'description' => 'Moderner Skin Fade mit sauberem Übergang und Styling'],
                    'ar' => ['name' => 'قص شعر سكين فيد', 'description' => 'قصة سكين فيد عصرية مع تدرج دقيق وتصفيف'],
                ],
                'price' => 35.00,
                'discount_price' => null,
                'duration_minutes' => 50,
                'color_code' => '#E91E63',
                'is_featured' => false,
            ],
            [
                'category' => 'Shave',
                'default' => [
                    'name' => 'Hot Towel Shave',
                    'description' => 'Traditional hot towel shave with straight razor finish',
                ],
                'translations' => [
                    'en' => ['name' => 'Hot Towel Shave', 'description' => 'Traditional hot towel shave with straight razor finish'],
                    'de' => ['name' => 'Heißtuchrasur', 'description' => 'Klassische Heißtuchrasur mit Rasiermesser-Finish'],
                    'ar' => ['name' => 'حلاقة بالمنشفة الساخنة', 'description' => 'حلاقة تقليدية بمنشفة ساخنة مع لمسة نهائية بالشفرة'],
                ],
                'price' => 24.00,
                'discount_price' => 20.00,
                'duration_minutes' => 30,
                'color_code' => '#9C27B0',
                'is_featured' => true,
            ],
            [
                'category' => 'Coloring',
                'default' => [
                    'name' => 'Grey Coverage Coloring',
                    'description' => 'Natural-looking color service to cover grey hair',
                ],
                'translations' => [
                    'en' => ['name' => 'Grey Coverage Coloring', 'description' => 'Natural-looking color service to cover grey hair'],
                    'de' => ['name' => 'Grauabdeckung Farbe', 'description' => 'Natürliche Farbauffrischung zur Abdeckung grauer Haare'],
                    'ar' => ['name' => 'صبغة تغطية الشيب', 'description' => 'خدمة صبغة طبيعية المظهر لتغطية الشعر الأبيض'],
                ],
                'price' => 45.00,
                'discount_price' => null,
                'duration_minutes' => 75,
                'color_code' => '#FF4081',
                'is_featured' => false,
            ],
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Haircut & Beard Combo',
                    'description' => 'Complete grooming package with haircut, beard trim, and styling',
                ],
                'translations' => [
                    'en' => ['name' => 'Haircut & Beard Combo', 'description' => 'Complete grooming package with haircut, beard trim, and styling'],
                    'de' => ['name' => 'Haarschnitt & Bart Kombi', 'description' => 'Komplettpaket mit Haarschnitt, Barttrimmen und Styling'],
                    'ar' => ['name' => 'باقة قص الشعر واللحية', 'description' => 'باقة عناية كاملة تشمل قص الشعر وتهذيب اللحية والتصفيف'],
                ],
                'price' => 52.00,
                'discount_price' => 47.00,
                'duration_minutes' => 90,
                'color_code' => '#F06292',
                'is_featured' => false,
            ],
        ];

        foreach ($services as $index => $serviceData) {
            $category = $categories->where('name', $serviceData['category'])->first();

            $service = Service::create([
                'category_id' => $category->id,
                'name' => $serviceData['default']['name'],
                'description' => $serviceData['default']['description'],
                'price' => $serviceData['price'],
                'discount_price' => $serviceData['discount_price'],
                'duration_minutes' => $serviceData['duration_minutes'],
                'is_active' => true,
                'sort_order' => $index + 1,
                'color_code' => $serviceData['color_code'],
                'is_featured' => $serviceData['is_featured'],
            ]);

            // Add translations
            foreach ($serviceData['translations'] as $locale => $attrs) {
                $service->translate($locale, [
                    'language_code' => $locale,
                    'name' => $attrs['name'],
                    'description' => $attrs['description'],
                ]);
            }
            self::create_service_image($service);

        }

        $this->command->info('Services seeded successfully with translations (' . count($services) . ' services)');

    }

    public static function create_service_image($service)
{
    $name=str_replace(' ','_',trim($service->name));
    $dir = "services/images/{$service->id}";
    $path = "{$dir}/{$name}_{$service->id}.png";

    Storage::disk('public')->makeDirectory($dir);

    if (Storage::disk('public')->exists($path)) {
        Storage::disk('public')->delete($path);
    }

    Avatar::create($service->name)
        ->setBackground($service->color_code ?? '#FF6B9D')
        ->setForeground('#FFFFFF')
        ->setDimension(400)
        ->save(storage_path("app/public/{$path}"));

    $service->image()->create([
        'instance_type' => Service::class,
        'instance_id' => $service->id,
        'name' => $name . '_' . $service->id,
        'path' => $path,
        'disk' => 'public',
        'type' => 'service_image',
        'extension' => 'png',
        'group' => 'service',
        'key' => 'main',
    ]);
}
}
