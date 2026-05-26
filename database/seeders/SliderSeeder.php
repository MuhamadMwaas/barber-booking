<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Slider;
use App\Models\SliderItem;
use App\Models\SliderItemTranslation;
use Illuminate\Database\Seeder;

class SliderSeeder extends Seeder
{

    public function run(): void
    {
        // جلب اللغات المتاحة
        $languages = Language::whereIn('code', ['en', 'ar', 'de'])->get()->keyBy('code');

        if ($languages->isEmpty()) {
            $this->command->warn('SliderSeeder: No languages found. Run LanguageSeeder first.');
            return;
        }

        // ── إنشاء السلايدر الرئيسي ──────────────────────────────────────────
        $homeSlider = Slider::updateOrCreate(
            ['key' => 'home'],
            ['name' => 'Home Page Slider', 'is_active' => true]
        );

        // ── الشريحة 1: دائمة (no scheduling) ──────────────────────────────
        $item1 = SliderItem::create([
            'slider_id'  => $homeSlider->id,
            'sort_order' => 1,
            'is_active'  => true,
            'starts_at'  => null, // دائم
            'ends_at'    => null, // دائم
        ]);

        $this->addTranslations($item1->id, $languages, [
            'en' => [
                'title'       => 'Welcome to Our Salon',
                'subtitle'    => 'Premium Barber & Beauty Services',
                'description' => 'Experience the finest haircuts and grooming with our expert stylists.',
            ],
            'ar' => [
                'title'       => 'مرحباً في صالوننا',
                'subtitle'    => 'خدمات حلاقة وتجميل احترافية',
                'description' => 'استمتع بأفضل تجربة حلاقة مع أمهر الحلاقين.',
            ],
            'de' => [
                'title'       => 'Willkommen in unserem Salon',
                'subtitle'    => 'Premium Friseur & Schönheitsdienstleistungen',
                'description' => 'Erleben Sie erstklassige Haarschnitte mit unseren Experten.',
            ],
        ]);

        $item2 = SliderItem::create([
            'slider_id'  => $homeSlider->id,
            'sort_order' => 2,
            'is_active'  => true,
            'starts_at'  => now()->startOfMonth(),
            'ends_at'    => now()->endOfMonth(),
        ]);

        $this->addTranslations($item2->id, $languages, [
            'en' => [
                'title'       => 'Monthly Special Offer',
                'subtitle'    => '20% Off All Services',
                'description' => 'Book now and enjoy our exclusive monthly discount.',
            ],
            'ar' => [
                'title'       => 'عرض الشهر الخاص',
                'subtitle'    => 'خصم 20% على جميع الخدمات',
                'description' => 'احجز الآن واستمتع بخصم شهرنا الحصري.',
            ],
            'de' => [
                'title'       => 'Monatliches Sonderangebot',
                'subtitle'    => '20% Rabatt auf alle Dienstleistungen',
                'description' => 'Jetzt buchen und unseren exklusiven Monatsrabatt genießen.',
            ],
        ]);

        // ── الشريحة 3: معطلة (is_active=false) ─────────────────────────────
        $item3 = SliderItem::create([
            'slider_id'  => $homeSlider->id,
            'sort_order' => 3,
            'is_active'  => false, // مخفية — للتجربة
            'starts_at'  => null,
            'ends_at'    => null,
        ]);

        $this->addTranslations($item3->id, $languages, [
            'en' => [
                'title'       => 'Coming Soon',
                'subtitle'    => 'New Services',
                'description' => 'Stay tuned for our upcoming new services.',
            ],
            'ar' => [
                'title'       => 'قريباً',
                'subtitle'    => 'خدمات جديدة',
                'description' => 'ترقبوا خدماتنا الجديدة القادمة.',
            ],
            'de' => [
                'title'       => 'Demnächst',
                'subtitle'    => 'Neue Dienstleistungen',
                'description' => 'Freuen Sie sich auf unsere neuen Dienstleistungen.',
            ],
        ]);

        $this->command->info("SliderSeeder: Created '{$homeSlider->key}' slider with 3 items (2 active, 1 disabled).");
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function addTranslations(int $itemId, $languages, array $data): void
    {
        foreach ($data as $code => $fields) {
            if (! isset($languages[$code])) {
                continue;
            }

            SliderItemTranslation::updateOrCreate(
                [
                    'slider_item_id' => $itemId,
                    'language_id'    => $languages[$code]->id,
                ],
                $fields
            );
        }
    }
}
