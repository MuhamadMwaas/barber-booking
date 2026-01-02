<?php

namespace Database\Seeders;

use App\Models\SamplePage;
use App\Models\PageTranslation;
use Illuminate\Database\Seeder;

class StaticPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTermsPage();
        $this->seedPrivacyPage();
    }

    /* ======================================================
     | Terms & Conditions
     |====================================================== */
    protected function seedTermsPage(): void
    {
        $page = SamplePage::updateOrCreate(
            ['page_key' => 'terms'],
            [
                'template'     => 'template.privacy',
                'is_published' => true,
                'version'      => 1,
            ]
        );

        $translations = [
            'ar' => [
                'title' => 'الشروط والأحكام',
                'content' => <<<HTML
<h2>مقدمة</h2>
<p>
باستخدامك لهذا التطبيق، فإنك توافق على الالتزام بالشروط والأحكام التالية.
</p>

<h2>استخدام الخدمة</h2>
<ul>
    <li>يجب استخدام الخدمة لأغراض قانونية فقط.</li>
    <li>يُمنع إساءة استخدام النظام.</li>
</ul>

<h2>المسؤولية</h2>
<p>
لا نتحمل أي مسؤولية عن الأضرار الناتجة عن سوء الاستخدام.
</p>
HTML,
                'meta' => [
                    'title' => 'الشروط والأحكام',
                    'description' => 'الشروط والأحكام الخاصة باستخدام التطبيق.',
                ],
            ],

            'en' => [
                'title' => 'Terms & Conditions',
                'content' => <<<HTML
<h2>Introduction</h2>
<p>
By using this application, you agree to comply with the following terms.
</p>

<h2>Use of Service</h2>
<ul>
    <li>The service must be used lawfully.</li>
    <li>System misuse is prohibited.</li>
</ul>

<h2>Liability</h2>
<p>
We are not liable for damages caused by misuse.
</p>
HTML,
                'meta' => [
                    'title' => 'Terms & Conditions',
                    'description' => 'Terms and conditions for using the application.',
                ],
            ],

            'de' => [
                'title' => 'Allgemeine Geschäftsbedingungen',
                'content' => <<<HTML
<h2>Einleitung</h2>
<p>
Durch die Nutzung dieser Anwendung stimmen Sie den folgenden Bedingungen zu.
</p>

<h2>Nutzung des Dienstes</h2>
<ul>
    <li>Der Dienst darf nur rechtmäßig genutzt werden.</li>
    <li>Missbrauch des Systems ist untersagt.</li>
</ul>

<h2>Haftung</h2>
<p>
Wir übernehmen keine Haftung für Schäden durch unsachgemäße Nutzung.
</p>
HTML,
                'meta' => [
                    'title' => 'AGB',
                    'description' => 'Allgemeine Geschäftsbedingungen der Anwendung.',
                ],
            ],
        ];

        $this->syncTranslations($page, $translations);
    }

    /* ======================================================
     | Privacy Policy
     |====================================================== */
    protected function seedPrivacyPage(): void
    {
        $page = SamplePage::updateOrCreate(
            ['page_key' => 'privacy'],
            [
                'template'     => 'template.privacy',
                'is_published' => true,
                'version'      => 1,
            ]
        );

        $translations = [
            'ar' => [
                'title' => 'سياسة الخصوصية',
                'content' => <<<HTML
<h2>جمع البيانات</h2>
<p>
نقوم بجمع البيانات الضرورية لتحسين جودة الخدمة.
</p>

<h2>حماية البيانات</h2>
<p>
نلتزم بحماية بياناتك وعدم مشاركتها مع أطراف غير مصرح لها.
</p>
HTML,
                'meta' => [
                    'title' => 'سياسة الخصوصية',
                    'description' => 'تعرف على كيفية تعاملنا مع بياناتك.',
                ],
            ],

            'en' => [
                'title' => 'Privacy Policy',
                'content' => <<<HTML
<h2>Data Collection</h2>
<p>
We collect necessary data to improve our services.
</p>

<h2>Data Protection</h2>
<p>
Your data is protected and never shared without permission.
</p>
HTML,
                'meta' => [
                    'title' => 'Privacy Policy',
                    'description' => 'Learn how we handle your personal data.',
                ],
            ],

            'de' => [
                'title' => 'Datenschutzerklärung',
                'content' => <<<HTML
<h2>Datenerhebung</h2>
<p>
Wir erheben notwendige Daten zur Verbesserung unserer Dienste.
</p>

<h2>Datenschutz</h2>
<p>
Ihre Daten werden geschützt und nicht ohne Zustimmung weitergegeben.
</p>
HTML,
                'meta' => [
                    'title' => 'Datenschutz',
                    'description' => 'Informationen zum Umgang mit personenbezogenen Daten.',
                ],
            ],
        ];

        $this->syncTranslations($page, $translations);
    }

    /* ======================================================
     | Helper
     |====================================================== */
    protected function syncTranslations(SamplePage $page, array $translations): void
    {
        foreach ($translations as $lang => $data) {
            PageTranslation::updateOrCreate(
                [
                    'page_id' => $page->id,
                    'lang'    => $lang,
                ],
                $data
            );
        }
    }
}
