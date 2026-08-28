<?php

namespace Database\Seeders;

use App\Models\LandingSection;
use Illuminate\Database\Seeder;

/**
 * Seeds the landing page with the approved design's own copy.
 *
 * German is the source text — it is what the design was written in and what the
 * salon's customers read; English and Arabic are translations of it. Every
 * string is stored as a locale map so the site can be switched without a
 * deploy.
 *
 * Re-running is safe: rows are matched on `key` and updated in place, so
 * `db:seed --class=LandingPageSeeder` restores the shipped copy after someone
 * has experimented in the admin panel.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sections() as $key => $definition) {
            LandingSection::updateOrCreate(
                ['key' => $key],
                [
                    'is_active'  => $definition['is_active'] ?? true,
                    'sort_order' => $definition['sort_order'] ?? 0,
                    'content'    => $definition['content'] ?? [],
                ],
            );
        }

        LandingSection::flushCache();
    }

    /**
     * Shorthand for a translation map.
     *
     * @return array{de: string, en: string, ar: string}
     */
    private function t(string $de, string $en, string $ar): array
    {
        return ['de' => $de, 'en' => $en, 'ar' => $ar];
    }

    /** @return array<string, array<string, mixed>> */
    private function sections(): array
    {
        return [

            /* ══════════════════ Brand ══════════════════ */
            LandingSection::BRAND => [
                'content' => [
                    'logo'    => '',
                    'favicon' => '',
                    'name'    => $this->t('LOOK UP', 'LOOK UP', 'لوك أب'),
                    'suffix'  => $this->t('FRISEUR', 'BARBER', 'صالون'),
                    'tagline' => $this->t(
                        'Ihr Stil. Unser Handwerk.',
                        'Your style. Our craft.',
                        'أسلوبك. حِرفتنا.',
                    ),
                ],
            ],

            /* ══════════════════ SEO ══════════════════ */
            LandingSection::SEO => [
                'content' => [
                    'title' => $this->t(
                        'LOOK UP FRISEUR — Ihr Stil. Unser Handwerk.',
                        'LOOK UP Barber — Your style. Our craft.',
                        'لوك أب — أسلوبك. حِرفتنا.',
                    ),
                    'description' => $this->t(
                        'Professionelle Haarschnitte, moderne Stylings, Pflege und Bartpflege in entspannter Atmosphäre. Termin bequem über unsere App buchen.',
                        'Professional haircuts, modern styling, treatments and beard care in a relaxed atmosphere. Book your appointment through our app.',
                        'قصات شعر احترافية وتصفيف عصري وعناية وحلاقة لحية في أجواء مريحة. احجز موعدك بسهولة عبر تطبيقنا.',
                    ),
                    'keywords' => $this->t(
                        'Friseur, Haarschnitt, Styling, Bartpflege, Barbershop, Termin buchen',
                        'barber, haircut, styling, beard care, barbershop, book appointment',
                        'صالون حلاقة, قص شعر, تصفيف, عناية باللحية, حجز موعد',
                    ),
                    'og_image' => '',
                ],
            ],

            /* ══════════════════ Navbar ══════════════════ */
            LandingSection::NAVBAR => [
                'content' => [
                    // Empty on purpose: the view then falls back to route('landing.app'),
                    // so the booking CTA keeps working even if the URL is cleared.
                    'cta_url'                => '',
                    'cta_label'              => $this->t('Termin buchen', 'Book now', 'احجز موعدك'),
                    'show_language_switcher' => true,
                    'links'                  => [
                        ['url' => '#hero',     'label' => $this->t('Startseite', 'Home', 'الرئيسية')],
                        ['url' => '#features', 'label' => $this->t('Über uns', 'About us', 'من نحن')],
                        ['url' => '#services', 'label' => $this->t('Leistungen', 'Services', 'خدماتنا')],
                        ['url' => '#gallery',  'label' => $this->t('Galerie', 'Gallery', 'المعرض')],
                        ['url' => '#info',     'label' => $this->t('Kontakt', 'Contact', 'تواصل معنا')],
                    ],
                ],
            ],

            /* ══════════════════ Hero ══════════════════ */
            LandingSection::HERO => [
                'sort_order' => 10,
                'content'    => [
                    'eyebrow'    => $this->t('Ihr Stil. Unser Handwerk.', 'Your style. Our craft.', 'أسلوبك. حِرفتنا.'),
                    'title_gold' => $this->t('LOOKUP', 'LOOKUP', 'لوك أب'),
                    'title_light'=> $this->t('FRISEUR', 'BARBER', 'للحلاقة'),
                    'description'=> $this->t(
                        "Erleben Sie professionelle Haarschnitte, moderne Stylings und erstklassigen Service in entspannter Atmosphäre.\nBei uns steht Ihr Look im Mittelpunkt.",
                        "Experience professional haircuts, modern styling and first-class service in a relaxed atmosphere.\nYour look is what we are here for.",
                        "استمتع بقصات شعر احترافية وتصفيف عصري وخدمة من الطراز الأول في أجواء مريحة.\nإطلالتك هي محور اهتمامنا.",
                    ),
                    'image'     => '',
                    'image_alt' => $this->t(
                        'Empfangsbereich des LOOK UP Friseursalons',
                        'The LOOK UP barbershop reception area',
                        'منطقة الاستقبال في صالون لوك أب',
                    ),

                    'primary_cta_label'   => $this->t('Termin buchen', 'Book now', 'احجز موعدك'),
                    'primary_cta_url'     => '',
                    'secondary_cta_label' => $this->t('Unsere Leistungen', 'Our services', 'خدماتنا'),
                    'secondary_cta_url'   => '#services',

                    'badges' => [
                        ['icon' => 'scissors', 'label' => $this->t('Erfahrene Stylisten', 'Experienced stylists', 'مصففون ذوو خبرة')],
                        ['icon' => 'bottle',   'label' => $this->t('Premium Produkte', 'Premium products', 'منتجات فاخرة')],
                        ['icon' => 'chat',     'label' => $this->t('Persönliche Beratung', 'Personal advice', 'استشارة شخصية')],
                    ],
                ],
            ],

            /* ══════════════════ Services ══════════════════ */
            LandingSection::SERVICES => [
                'sort_order' => 20,
                'content'    => [
                    'eyebrow'    => $this->t('Unsere Leistungen', 'Our services', 'خدماتنا'),
                    'title'      => $this->t('Für Ihren perfekten Look', 'For your perfect look', 'من أجل إطلالتك المثالية'),
                    'link_label' => $this->t('Mehr erfahren', 'Learn more', 'اعرف المزيد'),
                    'items'      => [
                        [
                            'icon'  => 'scissors',
                            'image' => '',
                            'url'   => '#info',
                            'title' => $this->t('Haarschnitt', 'Haircut', 'قص الشعر'),
                            'description' => $this->t(
                                'Individuelle Schnitte für Damen, Herren und Kinder.',
                                'Individual cuts for women, men and children.',
                                'قصات مخصّصة للسيدات والرجال والأطفال.',
                            ),
                        ],
                        [
                            'icon'  => 'comb',
                            'image' => '',
                            'url'   => '#info',
                            'title' => $this->t('Styling', 'Styling', 'التصفيف'),
                            'description' => $this->t(
                                'Moderne Stylings für jeden Anlass und jeden Typ.',
                                'Modern styling for every occasion and every type.',
                                'تصفيف عصري يناسب كل مناسبة وكل إطلالة.',
                            ),
                        ],
                        [
                            'icon'  => 'droplet',
                            'image' => '',
                            'url'   => '#info',
                            'title' => $this->t('Pflege & Treatment', 'Care & treatment', 'العناية والعلاج'),
                            'description' => $this->t(
                                'Professionelle Pflege für gesundes und strahlendes Haar.',
                                'Professional care for healthy, radiant hair.',
                                'عناية احترافية لشعر صحي ولامع.',
                            ),
                        ],
                        [
                            'icon'  => 'razor',
                            'image' => '',
                            'url'   => '#info',
                            'title' => $this->t('Bartpflege', 'Beard care', 'العناية باللحية'),
                            'description' => $this->t(
                                'Präzise Bartpflege und Rasur mit heißem Tuch.',
                                'Precise beard grooming and a hot-towel shave.',
                                'تهذيب دقيق للحية وحلاقة بالمنشفة الساخنة.',
                            ),
                        ],
                    ],
                ],
            ],

            /* ══════════════════ Features ══════════════════ */
            LandingSection::FEATURES => [
                'sort_order' => 30,
                'content'    => [
                    'eyebrow' => $this->t('Warum LOOKUP Friseur?', 'Why LOOKUP?', 'لماذا لوك أب؟'),
                    'title'   => $this->t(
                        'Mehr als nur ein Friseurbesuch',
                        'More than just a haircut',
                        'أكثر من مجرد زيارة لصالون حلاقة',
                    ),
                    'description' => $this->t(
                        'Wir bieten Ihnen nicht nur erstklassige Leistungen, sondern ein Rundum-Erlebnis, bei dem Sie sich wohlfühlen.',
                        'We offer more than first-class services — a complete experience you feel at home in.',
                        'نقدّم لك أكثر من خدمات من الدرجة الأولى: تجربة متكاملة تشعر فيها بالراحة.',
                    ),
                    'cta_label' => $this->t('Mehr über uns', 'More about us', 'المزيد عنا'),
                    'cta_url'   => '#info',
                    'items'     => [
                        [
                            'icon'  => 'users',
                            'title' => $this->t('Professionelles Team', 'Professional team', 'فريق محترف'),
                            'description' => $this->t(
                                'Unsere Stylisten sind erfahren und immer auf dem neuesten Stand.',
                                'Our stylists are experienced and always up to date.',
                                'مصففونا يمتلكون خبرة واسعة ويواكبون كل جديد.',
                            ),
                        ],
                        [
                            'icon'  => 'bottle',
                            'title' => $this->t('Hochwertige Produkte', 'High-quality products', 'منتجات عالية الجودة'),
                            'description' => $this->t(
                                'Wir verwenden nur Produkte ausgewählter Premium-Marken.',
                                'We work only with selected premium brands.',
                                'نستخدم منتجات علامات تجارية فاخرة مختارة بعناية.',
                            ),
                        ],
                        [
                            'icon'  => 'chair',
                            'title' => $this->t('Entspannte Atmosphäre', 'Relaxed atmosphere', 'أجواء مريحة'),
                            'description' => $this->t(
                                'Genießen Sie Ihren Besuch in einem stilvollen Ambiente.',
                                'Enjoy your visit in a stylish setting.',
                                'استمتع بزيارتك في أجواء أنيقة.',
                            ),
                        ],
                        [
                            'icon'  => 'chat',
                            'title' => $this->t('Persönliche Beratung', 'Personal advice', 'استشارة شخصية'),
                            'description' => $this->t(
                                'Wir nehmen uns Zeit für Sie und Ihre Wünsche.',
                                'We take the time to listen to what you want.',
                                'نخصّص وقتاً لك ولما ترغب به.',
                            ),
                        ],
                    ],
                ],
            ],

            /* ══════════════════ Gallery ══════════════════ */
            LandingSection::GALLERY => [
                'sort_order' => 40,
                'content'    => [
                    'eyebrow' => $this->t('Galerie', 'Gallery', 'المعرض'),
                    'title'   => $this->t('Unsere Arbeiten', 'Our work', 'أعمالنا'),
                    // Left empty on purpose: the button only renders once a real
                    // destination (usually Instagram) has been entered.
                    'cta_label' => $this->t('Mehr ansehen', 'View more', 'شاهد المزيد'),
                    'cta_url'   => '',
                    'items'     => [
                        ['image' => '', 'is_wide' => false],
                        ['image' => '', 'is_wide' => false],
                        ['image' => '', 'is_wide' => true],
                        ['image' => '', 'is_wide' => false],
                        ['image' => '', 'is_wide' => false],
                    ],
                ],
            ],

            /* ══════════════════ CTA ══════════════════ */
            LandingSection::CTA => [
                'sort_order' => 50,
                'content'    => [
                    'icon'  => 'calendar',
                    'title' => $this->t(
                        'Bereit für Ihren neuen Look?',
                        'Ready for your new look?',
                        'جاهز لإطلالتك الجديدة؟',
                    ),
                    'description' => $this->t(
                        'Buchen Sie jetzt Ihren Termin online – schnell, einfach und rund um die Uhr.',
                        'Book your appointment online — fast, simple and around the clock.',
                        'احجز موعدك الآن عبر الإنترنت — بسرعة وسهولة وعلى مدار الساعة.',
                    ),
                    'button_label' => $this->t('Termin buchen', 'Book now', 'احجز موعدك'),
                    'button_url'   => '',
                ],
            ],

            /* ══════════════════ Info strip ══════════════════ */
            LandingSection::INFO => [
                'sort_order' => 60,
                'content'    => [
                    'items' => [
                        [
                            'icon'  => 'map-pin',
                            'label' => $this->t('Adresse', 'Address', 'العنوان'),
                            'value' => $this->t(
                                "Musterstraße 123\n12345 Musterstadt",
                                "Musterstrasse 123\n12345 Musterstadt",
                                "شارع Musterstraße ١٢٣\n١٢٣٤٥ Musterstadt",
                            ),
                            'url' => '',
                        ],
                        [
                            'icon'  => 'phone',
                            'label' => $this->t('Telefon', 'Phone', 'الهاتف'),
                            'value' => $this->t('+49 123 4567890', '+49 123 4567890', '+49 123 4567890'),
                            'url'   => 'tel:+491234567890',
                        ],
                        [
                            'icon'  => 'clock',
                            'label' => $this->t('Öffnungszeiten', 'Opening hours', 'أوقات الدوام'),
                            'value' => $this->t(
                                "Mo – Fr: 09:00 – 19:00\nSa: 09:00 – 16:00",
                                "Mon – Fri: 09:00 – 19:00\nSat: 09:00 – 16:00",
                                "الاثنين – الجمعة: ٠٩:٠٠ – ١٩:٠٠\nالسبت: ٠٩:٠٠ – ١٦:٠٠",
                            ),
                            'url' => '',
                        ],
                    ],
                    'social_label' => $this->t('Folge uns', 'Follow us', 'تابعنا'),
                    'social_links' => [
                        ['platform' => 'facebook',  'url' => 'https://facebook.com/'],
                        ['platform' => 'instagram', 'url' => 'https://instagram.com/'],
                        ['platform' => 'whatsapp',  'url' => 'https://wa.me/491234567890'],
                    ],
                ],
            ],

            /* ══════════════════ Footer ══════════════════ */
            LandingSection::FOOTER => [
                'content' => [
                    'tagline' => $this->t(
                        'Ihr Stil. Unser Handwerk.',
                        'Your style. Our craft.',
                        'أسلوبك. حِرفتنا.',
                    ),
                    'closing' => $this->t(
                        'Wir freuen uns auf Ihren Besuch!',
                        'We look forward to your visit!',
                        'يسعدنا استقبالك!',
                    ),

                    'nav_title' => $this->t('Navigation', 'Navigation', 'التنقل'),
                    'nav_links' => [
                        ['url' => '#hero',     'label' => $this->t('Startseite', 'Home', 'الرئيسية')],
                        ['url' => '#features', 'label' => $this->t('Über uns', 'About us', 'من نحن')],
                        ['url' => '#services', 'label' => $this->t('Leistungen', 'Services', 'خدماتنا')],
                        ['url' => '#gallery',  'label' => $this->t('Galerie', 'Gallery', 'المعرض')],
                        ['url' => '#info',     'label' => $this->t('Kontakt', 'Contact', 'تواصل معنا')],
                    ],

                    'services_title' => $this->t('Leistungen', 'Services', 'الخدمات'),
                    'services_links' => [
                        ['url' => '#services', 'label' => $this->t('Haarschnitt', 'Haircut', 'قص الشعر')],
                        ['url' => '#services', 'label' => $this->t('Styling', 'Styling', 'التصفيف')],
                        ['url' => '#services', 'label' => $this->t('Pflege & Treatment', 'Care & treatment', 'العناية والعلاج')],
                        ['url' => '#services', 'label' => $this->t('Bartpflege', 'Beard care', 'العناية باللحية')],
                        ['url' => '#services', 'label' => $this->t('Farbe & Strähnen', 'Colour & highlights', 'الصبغ والخصل')],
                        ['url' => '#services', 'label' => $this->t('Kinderhaarschnitt', "Children's haircut", 'قص شعر الأطفال')],
                    ],

                    'newsletter_enabled'     => true,
                    'newsletter_title'       => $this->t('Newsletter', 'Newsletter', 'النشرة البريدية'),
                    'newsletter_description' => $this->t(
                        'Bleiben Sie auf dem Laufenden mit unseren Neuigkeiten.',
                        'Stay up to date with our news.',
                        'ابقَ على اطلاع بآخر أخبارنا.',
                    ),
                    'newsletter_placeholder' => $this->t('E-Mail-Adresse', 'Email address', 'البريد الإلكتروني'),
                    // No mailing-list provider is configured yet, so the footer
                    // shows the mailto fallback rather than a form that would
                    // silently discard addresses.
                    'newsletter_action_url'  => '',
                    'newsletter_field_name'  => 'EMAIL',
                    'newsletter_email'       => 'info@lookupfriseur.de',
                    'newsletter_button_label'=> $this->t('Schreiben Sie uns', 'Write to us', 'راسلنا'),

                    'copyright' => $this->t(
                        '© ' . date('Y') . ' LOOK UP FRISEUR. Alle Rechte vorbehalten.',
                        '© ' . date('Y') . ' LOOK UP FRISEUR. All rights reserved.',
                        '© ' . date('Y') . ' LOOK UP FRISEUR. جميع الحقوق محفوظة.',
                    ),
                    'legal_links' => [
                        ['url' => '/page/impressum',       'label' => $this->t('Impressum', 'Imprint', 'بيانات الناشر')],
                        ['url' => '/page/privacy-policy',  'label' => $this->t('Datenschutz', 'Privacy', 'سياسة الخصوصية')],
                        ['url' => '/page/terms-conditions','label' => $this->t('AGB', 'Terms', 'شروط الاستخدام')],
                    ],
                ],
            ],

            /* ══════════════════ App page ══════════════════ */
            LandingSection::APP_PAGE => [
                'content' => [
                    'eyebrow' => $this->t('LOOK UP App', 'LOOK UP App', 'تطبيق لوك أب'),
                    'title'   => $this->t(
                        'Termin buchen in der App',
                        'Book your appointment in the app',
                        'احجز موعدك من التطبيق',
                    ),
                    'description' => $this->t(
                        "Ihre Termine buchen, verschieben und im Blick behalten — jederzeit, direkt vom Handy.\nLaden Sie die LOOK UP App und sichern Sie sich Ihren Wunschtermin.",
                        "Book, reschedule and keep track of your appointments — any time, straight from your phone.\nDownload the LOOK UP app and secure the slot you want.",
                        "احجز مواعيدك وعدّلها وتابعها في أي وقت مباشرة من هاتفك.\nحمّل تطبيق لوك أب واحجز الموعد الذي يناسبك.",
                    ),

                    // Filled in once the app is published; until then the page
                    // renders the explanatory note instead of dead buttons.
                    'app_store_url'     => '',
                    'google_play_url'   => '',
                    'app_store_label'   => $this->t('App Store', 'App Store', 'App Store'),
                    'google_play_label' => $this->t('Google Play', 'Google Play', 'Google Play'),
                    'coming_soon_note'  => $this->t(
                        'Die App ist in Kürze verfügbar. Rufen Sie uns bis dahin gerne an — wir buchen Ihren Termin persönlich.',
                        'The app is coming soon. Until then, just give us a call and we will book your appointment for you.',
                        'التطبيق متاح قريباً. حتى ذلك الحين اتصل بنا وسنحجز لك موعدك.',
                    ),

                    'mockup_image' => '',
                    'qr_image'     => '',
                    'qr_note'      => $this->t(
                        'Code scannen und die App direkt installieren.',
                        'Scan the code to install the app right away.',
                        'امسح الرمز لتثبيت التطبيق مباشرة.',
                    ),

                    'features' => [
                        [
                            'icon'  => 'calendar',
                            'title' => $this->t('Rund um die Uhr buchen', 'Book around the clock', 'احجز على مدار الساعة'),
                            'description' => $this->t(
                                'Freie Zeiten sehen und sofort buchen — auch nach Ladenschluss.',
                                'See free slots and book instantly — even after closing time.',
                                'اطّلع على الأوقات المتاحة واحجز فوراً حتى بعد إغلاق المحل.',
                            ),
                        ],
                        [
                            'icon'  => 'bell',
                            'title' => $this->t('Erinnerungen', 'Reminders', 'تذكيرات'),
                            'description' => $this->t(
                                'Eine Benachrichtigung vor jedem Termin — nichts geht mehr unter.',
                                'A notification before every appointment, so nothing slips.',
                                'إشعار قبل كل موعد حتى لا يفوتك شيء.',
                            ),
                        ],
                        [
                            'icon'  => 'users',
                            'title' => $this->t('Ihr Stylist', 'Your stylist', 'مصفّفك المفضل'),
                            'description' => $this->t(
                                'Wählen Sie die Person, bei der Sie am liebsten sitzen.',
                                'Choose the person you like sitting with most.',
                                'اختر الشخص الذي تفضّل أن يعتني بك.',
                            ),
                        ],
                        [
                            'icon'  => 'clock',
                            'title' => $this->t('Ihre Historie', 'Your history', 'سجلّ زياراتك'),
                            'description' => $this->t(
                                'Alle vergangenen Besuche und Leistungen auf einen Blick.',
                                'Every past visit and service at a glance.',
                                'كل زياراتك وخدماتك السابقة في مكان واحد.',
                            ),
                        ],
                    ],

                    'steps_eyebrow' => $this->t('So einfach geht es', 'How it works', 'بهذه البساطة'),
                    'steps_title'   => $this->t('In drei Schritten zum Termin', 'Three steps to your appointment', 'ثلاث خطوات إلى موعدك'),
                    'steps'         => [
                        [
                            'title' => $this->t('App laden', 'Get the app', 'حمّل التطبيق'),
                            'description' => $this->t(
                                'Kostenlos für iPhone und Android.',
                                'Free for iPhone and Android.',
                                'مجاناً لأجهزة آيفون وأندرويد.',
                            ),
                        ],
                        [
                            'title' => $this->t('Leistung wählen', 'Pick a service', 'اختر الخدمة'),
                            'description' => $this->t(
                                'Leistung, Stylist und Uhrzeit in wenigen Sekunden.',
                                'Service, stylist and time in a few seconds.',
                                'الخدمة والمصفّف والوقت خلال ثوانٍ.',
                            ),
                        ],
                        [
                            'title' => $this->t('Fertig', 'Done', 'تم'),
                            'description' => $this->t(
                                'Bestätigung sofort, Erinnerung vor dem Termin.',
                                'Instant confirmation and a reminder before your visit.',
                                'تأكيد فوري وتذكير قبل الموعد.',
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }
}
