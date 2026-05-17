<?php

namespace Database\Seeders;

use App\Models\AboutUsPage;
use App\Models\AboutUsTeamMember;
use Illuminate\Database\Seeder;

class AboutUsPageSeeder extends Seeder
{
    public function run(): void
    {
        $page = AboutUsPage::updateOrCreate(
            ['id' => 1],
            [
                // ── Hero ─────────────────────────────────────────────────────
                'hero_title' => [
                    'de' => 'Über uns',
                    'ar' => 'من نحن',
                    'en' => 'About Us',
                ],
                'hero_subtitle' => [
                    'de' => 'Willkommen in unserem Salon',
                    'ar' => 'مرحبًا بكم في صالوننا',
                    'en' => 'Welcome to Our Salon',
                ],
                'hero_description' => [
                    'de' => 'Unser Salon zeichnet sich durch Qualität, Eleganz und Leidenschaft aus. Wir bieten Ihnen ein luxuriöses Erlebnis in ruhiger Atmosphäre für einen Look, der perfekt zu Ihnen passt.',
                    'ar' => 'يتميز صالوننا بالجودة، الأناقة والشغف.. نقدم لك تجربة فاخرة في أجواء هادئة للحصول على إطلالة مثالية تناسبك.',
                    'en' => 'Our salon stands out for its quality, elegance and passion. We offer you a luxurious experience in a peaceful atmosphere for a look that perfectly suits you.',
                ],

                // ── Contact ──────────────────────────────────────────────────
                'contact_phone' => [
                    'value' => '+49 30 12345678',
                    'label' => ['de' => 'Telefon', 'ar' => 'الهاتف', 'en' => 'Phone'],
                    'icon'  => 'heroicon-o-phone',
                ],
                'contact_address' => [
                    'value' => 'Hauwitzstraße 123, 10115 Berlin',
                    'label' => ['de' => 'Adresse', 'ar' => 'العنوان', 'en' => 'Address'],
                    'icon'  => 'heroicon-o-map-pin',
                ],
                'contact_email' => 'info@lookup.de',
                'opening_hours' => [
                    'de' => 'Mo–Sa: 09:00–20:00 / So: geschlossen',
                    'ar' => 'الاثنين – السبت: 09:00 – 20:00 / الأحد: مغلق',
                    'en' => 'Mon–Sat: 09:00–20:00 / Sun: closed',
                ],

                // ── Social ────────────────────────────────────────────────────
                'social_title' => [
                    'de' => 'Folge uns',
                    'ar' => 'تابعنا',
                    'en' => 'Follow us',
                ],
                'social_links' => [
                    ['platform' => 'tiktok',    'url' => 'https://tiktok.com/@salon',   'icon' => 'heroicon-o-musical-note'],
                    ['platform' => 'facebook',  'url' => 'https://facebook.com/salon',  'icon' => 'heroicon-o-globe-alt'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/salon', 'icon' => 'heroicon-o-camera'],
                ],

                // ── Legal ─────────────────────────────────────────────────────
                'legal_links' => [
                    [
                        'key'   => 'privacy',
                        'label' => ['de' => 'Datenschutz',    'ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
                        'url'   => '/privacy',
                    ],
                    [
                        'key'   => 'terms',
                        'label' => ['de' => 'AGB',             'ar' => 'الشروط والأحكام', 'en' => 'Terms & Conditions'],
                        'url'   => '/terms',
                    ],
                    [
                        'key'   => 'impressum',
                        'label' => ['de' => 'Impressum',       'ar' => 'بيانات الناشر',   'en' => 'Imprint'],
                        'url'   => '/impressum',
                    ],
                ],

                // ── Features ─────────────────────────────────────────────────
                'features' => [
                    [
                        'icon'        => 'heroicon-o-scissors',
                        'title'       => [
                            'de' => 'Erfahrung & Können',
                            'ar' => 'خبرة ومهارة',
                            'en' => 'Experience & Skill',
                        ],
                        'description' => [
                            'de' => 'Jahre Erfahrung und kontinuierliche Weiterbildung, damit Sie stets die besten Dienstleistungen erhalten.',
                            'ar' => 'سنوات من الخبرة والتدريب المستمر لنقدم لك أفضل الخدمات.',
                            'en' => 'Years of experience and continuous training to bring you the best services.',
                        ],
                    ],
                    [
                        'icon'        => 'heroicon-o-star',
                        'title'       => [
                            'de' => 'Luxusprodukte',
                            'ar' => 'منتجات فاخرة',
                            'en' => 'Premium Products',
                        ],
                        'description' => [
                            'de' => 'Wir verwenden ausschließlich hochwertige Produkte für optimale Ergebnisse.',
                            'ar' => 'نستخدم منتجات عالية الجودة لأفضل النتائج.',
                            'en' => 'We use only high-quality products for the best results.',
                        ],
                    ],
                    [
                        'icon'        => 'heroicon-o-user',
                        'title'       => [
                            'de' => 'Persönliche Beratung',
                            'ar' => 'استشارة شخصية',
                            'en' => 'Personal Consultation',
                        ],
                        'description' => [
                            'de' => 'Wir gestalten Ihren Look so, dass er Ihre Persönlichkeit und Ihren Stil perfekt widerspiegelt.',
                            'ar' => 'نصمم إطلالتك بما يعكس شخصيتك ويناسب أسلوبك.',
                            'en' => 'We design your look to perfectly reflect your personality and style.',
                        ],
                    ],
                ],

                // ── Newsletter ────────────────────────────────────────────────
                'newsletter_title' => [
                    'de' => 'Bleib auf dem Laufenden',
                    'ar' => 'ابق على اطلاع دائم',
                    'en' => 'Stay Up to Date',
                ],
                'newsletter_description' => [
                    'de' => 'Abonniere unseren Newsletter und erhalte exklusive Angebote und Neuigkeiten direkt in dein Postfach.',
                    'ar' => 'اشترك في نشرتنا الإخبارية واحصل على عروض حصرية وأخبار جديدة مباشرة في بريدك.',
                    'en' => 'Subscribe to our newsletter and receive exclusive offers and news directly in your inbox.',
                ],
                'newsletter_enabled' => true,
                'is_active'          => true,
            ]
        );

        // ── Team Members ─────────────────────────────────────────────────────
        AboutUsTeamMember::updateOrCreate(
            ['about_us_page_id' => $page->id, 'sort_order' => 0],
            [
                'name' => [
                    'de' => 'Yuren',
                    'ar' => 'يُرَن',
                    'en' => 'Yuren',
                ],
                'position' => [
                    'de' => 'Friseur & Hairstylist',
                    'ar' => 'حلاق ومصفف شعر',
                    'en' => 'Barber & Hair Stylist',
                ],
                'description' => [
                    'de' => 'Über 8 Jahre Erfahrung im modernen Herrenhaarschnitt, Farbtechniken und Bartgestaltung. Spezialist für präzise Schnitte und ausgefallene Styles.',
                    'ar' => 'أكثر من 8 سنوات من الخبرة في الحلاقة الرجالية الحديثة، تقنيات الألوان وتصميم اللحية. متخصص في القصات الدقيقة والأساليب المميزة.',
                    'en' => 'Over 8 years of experience in modern men\'s haircuts, color techniques and beard design. Specialist in precise cuts and distinctive styles.',
                ],
                'image'      => null,
                'sort_order' => 0,
                'is_active'  => true,
            ]
        );

        AboutUsPage::clearCache();

        $this->command->info('AboutUs page seeded successfully.');
    }
}
