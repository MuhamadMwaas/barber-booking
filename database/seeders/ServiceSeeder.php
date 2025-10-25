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
                    'description' => 'Professional haircut with styling',
                ],
                'translations' => [
                    'en' => ['name' => 'Men\'s Haircut', 'description' => 'Professional haircut with styling'],
                    'de' => ['name' => 'Herrenhaarschnitt', 'description' => 'Professioneller Haarschnitt mit Styling'],
                    'ar' => ['name' => 'قص شعر الرجال', 'description' => 'قص شعر احترافي مع تصفيف'],
                ],
                'price' => 150.00,
                'discount_price' => null,
                'duration_minutes' => 45,
                'color_code' => '#FF6B9D',
                'is_featured' => true,
            ],
            [
                'category' => 'Beard Trim',
                'default' => [
                    'name' => 'Hair Coloring - Full',
                    'description' => 'Complete hair coloring with premium products',
                ],
                'translations' => [
                    'en' => ['name' => 'Hair Coloring - Full', 'description' => 'Complete hair coloring with premium products'],
                    'de' => ['name' => 'Haarefärben - Voll', 'description' => 'Komplette Haarefärbung mit Premium-Produkten'],
                    'ar' => ['name' => 'صبغ الشعر - بالكامل', 'description' => 'صبغ الشعر بالكامل بمنتجات متميزة'],
                ],
                'price' => 450.00,
                'discount_price' => 399.00,
                'duration_minutes' => 180,
                'color_code' => '#C724B1',
                'is_featured' => true,
            ],
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Hair Highlights',
                    'description' => 'Professional hair highlighting',
                ],
                'translations' => [
                    'en' => ['name' => 'Hair Highlights', 'description' => 'Professional hair highlighting'],
                    'de' => ['name' => 'Haare Strähnchen', 'description' => 'Professionelles Haare Strähnchen'],
                    'ar' => ['name' => 'هايلايت الشعر', 'description' => 'هايلايت احترافي للشعر'],
                ],
                'price' => 350.00,
                'discount_price' => null,
                'duration_minutes' => 150,
                'color_code' => '#E91E63',
                'is_featured' => false,
            ],
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Keratin Treatment',
                    'description' => 'Brazilian keratin hair smoothing treatment',
                ],
                'translations' => [
                    'en' => ['name' => 'Keratin Treatment', 'description' => 'Brazilian keratin hair smoothing treatment'],
                    'de' => ['name' => 'Keratin-Behandlung', 'description' => 'Brasilianische Keratin-Haarglättungsbehandlung'],
                    'ar' => ['name' => 'علاج الكيراتين', 'description' => 'علاج تنعيم الشعر بالكيراتين البرازيلي'],
                ],
                'price' => 800.00,
                'discount_price' => 699.00,
                'duration_minutes' => 240,
                'color_code' => '#9C27B0',
                'is_featured' => true,
            ],
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Blow Dry & Styling',
                    'description' => 'Professional blow dry with styling',
                ],
                'translations' => [
                    'en' => ['name' => 'Blow Dry & Styling', 'description' => 'Professional blow dry with styling'],
                    'de' => ['name' => 'Föhnen & Styling', 'description' => 'Professionelles Föhnen mit Styling'],
                    'ar' => ['name' => 'تجفيف وتصفيف الشعر', 'description' => 'تجفيف احترافي للشعر مع تصفيف'],
                ],
                'price' => 100.00,
                'discount_price' => null,
                'duration_minutes' => 30,
                'color_code' => '#FF4081',
                'is_featured' => false,
            ],
            [
                'category' => 'Haircut',
                'default' => [
                    'name' => 'Hair Spa Treatment',
                    'description' => 'Deep conditioning and nourishing hair treatment',
                ],
                'translations' => [
                    'en' => ['name' => 'Hair Spa Treatment', 'description' => 'Deep conditioning and nourishing hair treatment'],
                    'de' => ['name' => 'Haare Spa-Behandlung', 'description' => 'Tiefenpflegende und nährende Haarbehandlung'],
                    'ar' => ['name' => 'علاج تجميل الشعر', 'description' => 'علاج ترطيب وتغذية الشعر العميق'],
                ],
                'price' => 200.00,
                'discount_price' => null,
                'duration_minutes' => 60,
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
