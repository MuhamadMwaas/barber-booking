<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Seeder for the Legal Notice page
 * (بيانات النشر / Impressum / Legal Notice).
 *
 * Run with:
 *   php artisan db:seed --class=ImpressumPageSeeder
 */
class ImpressumPageSeeder extends Seeder
{
    public function run(): void
    {
        CmsPage::where('slug', 'impressum')->delete();

        CmsPage::create([
            'name'      => 'بيانات النشر القانونية',
            'slug'      => 'impressum',
            'is_active' => true,
            'blocks'    => $this->blocks(),
        ]);

        $this->command->info('✅  Impressum (Legal Notice) page seeded.');
    }

    /* ══════════════════════════════════════════════════════════
     │  Block definitions
     ══════════════════════════════════════════════════════════ */

    private function blocks(): array
    {
        return [

            /* ─── Main title ─────────────────────────────────── */
            $this->heading('h1', [
                'ar' => 'بيانات النشر القانونية (Impressum)',
                'de' => 'Impressum',
                'en' => 'Legal Notice (Impressum)',
            ], 'auto', 'primary'),

            /* ─── Legal basis ────────────────────────────────── */
            $this->paragraph([
                'ar' => 'المعلومات وفقًا للمادة § 5 من قانون الوسائط الألماني (TMG)',
                'de' => 'Angaben gemäß § 5 TMG',
                'en' => 'Information in accordance with § 5 of the German Telemedia Act (TMG)',
            ]),

            $this->divider(),

            /* ─── Company info ───────────────────────────────── */
            $this->heading('h2', [
                'ar' => 'بيانات الشركة',
                'de' => 'Unternehmensangaben',
                'en' => 'Company Details',
            ]),

            $this->paragraph([
                'ar' => "Look up OHG\nRupprechtstraße 33\n84034 Landshut\nDeutschland\n\nالسجل التجاري: HRA 12712\nالمحكمة المختصة بالتسجيل: Amtsgericht Landshut",
                'de' => "Look up OHG\nRupprechtstraße 33\n84034 Landshut\nDeutschland\n\nHandelsregister: HRA 12712\nRegistergericht: Amtsgericht Landshut",
                'en' => "Look up OHG\nRupprechtstraße 33\n84034 Landshut\nGermany\n\nTrade Register: HRA 12712\nRegistry Court: Amtsgericht Landshut",
            ]),

            $this->divider(),

            /* ─── Legal representatives ──────────────────────── */
            $this->heading('h2', [
                'ar' => 'الممثلون القانونيون',
                'de' => 'Vertreten durch',
                'en' => 'Legal Representatives',
            ]),

            $this->paragraph([
                'ar' => 'Luay Rakik & Nasradin Albarho',
                'de' => 'Luay Rakik & Nasradin Albarho',
                'en' => 'Luay Rakik & Nasradin Albarho',
            ]),

            $this->divider(),

            /* ─── Contact ────────────────────────────────────── */
            $this->heading('h2', [
                'ar' => 'معلومات التواصل',
                'de' => 'Kontakt',
                'en' => 'Contact',
            ]),

            $this->paragraph([
                'ar' => "الهاتف: +49 0871 / 6877271\nالبريد الإلكتروني: info@lookupfriseur.de",
                'de' => "Telefon: +49 0871 / 6877271\nE-Mail: info@lookupfriseur.de",
                'en' => "Phone: +49 0871 / 6877271\nE-Mail: info@lookupfriseur.de",
            ]),

            $this->divider(),

            /* ─── VAT ID ─────────────────────────────────────── */
            $this->heading('h2', [
                'ar' => 'رقم ضريبة القيمة المضافة',
                'de' => 'Umsatzsteuer-ID',
                'en' => 'VAT Identification Number',
            ]),

            $this->paragraph([
                'ar' => "رقم تعريف ضريبة القيمة المضافة وفقًا للمادة § 27 a من قانون ضريبة القيمة المضافة الألماني:\nDE367619660",
                'de' => "Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:\nDE367619660",
                'en' => "VAT identification number pursuant to § 27 a of the German Value Added Tax Act:\nDE367619660",
            ]),

            $this->divider(),

            /* ─── Professional designation ───────────────────── */
            $this->heading('h2', [
                'ar' => 'المسمى المهني والتنظيمات المهنية',
                'de' => 'Angaben zur Berufsbezeichnung',
                'en' => 'Professional Designation',
            ]),

            $this->paragraph([
                'ar' => "المسمى المهني: أستاذة حلاقة (Friseurmeisterin)\nتم منح اللقب في: ألمانيا",
                'de' => "Berufsbezeichnung: Friseurmeisterin\nVerliehen in: Deutschland",
                'en' => "Professional title: Master Hairdresser (Friseurmeisterin)\nTitle granted in: Germany",
            ]),

            $this->divider(),

            /* ─── EU Dispute Resolution ──────────────────────── */
            $this->heading('h2', [
                'ar' => 'تسوية النزاعات داخل الاتحاد الأوروبي',
                'de' => 'EU-Streitschlichtung',
                'en' => 'EU Dispute Resolution',
            ]),

            $this->paragraph([
                'ar' => 'توفر المفوضية الأوروبية منصة لتسوية النزاعات عبر الإنترنت (OS):\nhttps://ec.europa.eu/consumers/odr\n\nيمكنكم العثور على عنوان بريدنا الإلكتروني أعلاه ضمن بيانات النشر القانونية.',
                'de' => 'Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:\nhttps://ec.europa.eu/consumers/odr\n\nUnsere E-Mail-Adresse finden Sie oben im Impressum.',
                'en' => 'The European Commission provides a platform for online dispute resolution (ODR):\nhttps://ec.europa.eu/consumers/odr\n\nOur e-mail address can be found above in this legal notice.',
            ]),

            $this->divider(),

            /* ─── Consumer Dispute Resolution ───────────────── */
            $this->heading('h2', [
                'ar' => 'تسوية نزاعات المستهلك / هيئة التحكيم العامة',
                'de' => 'Verbraucherstreitbeilegung / Universalschlichtungsstelle',
                'en' => 'Consumer Dispute Resolution',
            ]),

            $this->warningBox([
                'ar' => 'نحن غير مستعدين أو ملزمين بالمشاركة في إجراءات تسوية النزاعات أمام هيئة تحكيم للمستهلكين.',
                'de' => 'Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.',
                'en' => 'We are neither willing nor obliged to participate in dispute resolution proceedings before a consumer arbitration board.',
            ]),

            $this->divider(),

            /* ─── Footer ─────────────────────────────────────── */
            $this->paragraph([
                'ar' => 'آخر تحديث: مايو ٢٠٢٦',
                'de' => 'Stand: Mai 2026',
                'en' => 'Last updated: May 2026',
            ], 'center'),

        ];
    }

    /* ══════════════════════════════════════════════════════════
     │  Block builder helpers
     ══════════════════════════════════════════════════════════ */

    private function heading(
        string $level,
        array  $texts,
        string $alignment = 'auto',
        string $color     = 'default'
    ): array {
        return [
            'type'         => 'heading',
            'is_active'    => true,
            'props'        => ['level' => $level, 'alignment' => $alignment, 'color' => $color],
            'translations' => [
                'ar' => ['text' => $texts['ar'] ?? ''],
                'en' => ['text' => $texts['en'] ?? ''],
                'de' => ['text' => $texts['de'] ?? ''],
            ],
        ];
    }

    private function paragraph(array $texts, string $alignment = 'auto'): array
    {
        return [
            'type'         => 'paragraph',
            'is_active'    => true,
            'props'        => ['alignment' => $alignment, 'color' => 'default'],
            'translations' => [
                'ar' => ['text' => $texts['ar'] ?? ''],
                'en' => ['text' => $texts['en'] ?? ''],
                'de' => ['text' => $texts['de'] ?? ''],
            ],
        ];
    }

    private function warningBox(array $texts): array
    {
        return [
            'type'         => 'warning_box',
            'is_active'    => true,
            'props'        => ['background_color' => 'default', 'color' => 'default'],
            'translations' => [
                'ar' => ['text' => $texts['ar'] ?? ''],
                'en' => ['text' => $texts['en'] ?? ''],
                'de' => ['text' => $texts['de'] ?? ''],
            ],
        ];
    }

    private function divider(
        string $orientation = 'horizontal',
        string $size        = 'sm',
        string $color       = 'default'
    ): array {
        return [
            'type'         => 'divider',
            'is_active'    => true,
            'props'        => ['orientation' => $orientation, 'size' => $size, 'color' => $color],
            'translations' => ['ar' => [], 'en' => [], 'de' => []],
        ];
    }
}
