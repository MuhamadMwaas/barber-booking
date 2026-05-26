<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Seeder for the Privacy Policy page (سياسة الخصوصية / Datenschutzrichtlinie).
 *
 * Blocks follow the flat format consumed by CmsBlockNormalizer:
 *   { type, is_active, props, translations, …extra fields }
 *
 * Run with:
 *   php artisan db:seed --class=PrivacyPolicyPageSeeder
 */
class PrivacyPolicyPageSeeder extends Seeder
{
    public function run(): void
    {
        // Remove old record if it exists (idempotent)
        CmsPage::where('slug', 'privacy-policy')->delete();

        CmsPage::create([
            'name'      => 'سياسة الخصوصية',
            'slug'      => 'privacy-policy',
            'is_active' => true,
            'blocks'    => $this->blocks(),
        ]);

        $this->command->info('✅  Privacy Policy page seeded.');
    }

    /* ══════════════════════════════════════════════════════════
     │  Block definitions
     ══════════════════════════════════════════════════════════ */

    private function blocks(): array
    {
        return [

            /* ─── Main title ─────────────────────────────────── */
            $this->heading('h1', [
                'ar' => 'سياسة الخصوصية',
                'de' => 'Datenschutzrichtlinie – App „Look up"',
                'en' => 'Privacy Policy – Look up App',
            ], 'auto', 'primary'),

            /* ─── Section 1: Introduction ─────────────────────── */
            $this->heading('h2', [
                'ar' => '١. مقدمة',
                'de' => '1. Einleitung',
                'en' => '1. Introduction',
            ]),

            $this->paragraph([
                'ar' => 'نحن في Look up OHG نولي حماية البيانات الشخصية أهمية كبيرة. توضح سياسة الخصوصية هذه كيفية جمع ومعالجة وحفظ البيانات الشخصية عند استخدام تطبيق „Look up".',
                'de' => 'Wir, die Look up OHG, nehmen den Schutz personenbezogener Daten sehr ernst. Diese Datenschutzrichtlinie informiert Sie darüber, welche personenbezogenen Daten bei der Nutzung der App „Look up" erhoben, verarbeitet und gespeichert werden.',
                'en' => 'We, Look up OHG, take the protection of personal data very seriously. This Privacy Policy explains what personal data is collected, processed, and stored when you use the "Look up" app.',
            ]),

            $this->paragraph([
                'ar' => 'تتم معالجة البيانات الشخصية وفقًا لـ Datenschutz-Grundverordnung (DSGVO) والقوانين الألمانية المعمول بها لحماية البيانات.',
                'de' => 'Die Verarbeitung personenbezogener Daten erfolgt im Einklang mit der Datenschutz-Grundverordnung (DSGVO) sowie den geltenden deutschen Datenschutzbestimmungen.',
                'en' => 'Personal data is processed in accordance with the General Data Protection Regulation (GDPR) and applicable German data protection laws.',
            ]),

            $this->paragraph([
                'ar' => 'باستخدام التطبيق، فإنك توافق على سياسة الخصوصية هذه.',
                'de' => 'Durch die Nutzung der App erklären Sie sich mit dieser Datenschutzrichtlinie einverstanden.',
                'en' => 'By using the app, you agree to this Privacy Policy.',
            ]),

            $this->divider(),

            /* ─── Section 2: Data Controller ─────────────────── */
            $this->heading('h2', [
                'ar' => '٢. الجهة المسؤولة عن معالجة البيانات',
                'de' => '2. Verantwortlicher',
                'en' => '2. Data Controller',
            ]),

            $this->paragraph([
                'ar' => "الجهة المسؤولة عن معالجة البيانات هي:\n\nLook up OHG\nRupprechtstraße 33\n84034 Landshut\nDeutschland",
                'de' => "Verantwortlich für die Datenverarbeitung ist:\n\nLook up OHG\nRupprechtstraße 33\n84034 Landshut\nDeutschland",
                'en' => "The entity responsible for data processing is:\n\nLook up OHG\nRupprechtstraße 33\n84034 Landshut\nGermany",
            ]),

            $this->paragraph([
                'ar' => "يمثل الشركة: Luay Rakik & Nasradin Albarho\nالهاتف: +49 0871 / 6877271\nالبريد الإلكتروني: info@lookupfriseur.de\nالسجل التجاري: HRA 12712 – Amtsgericht Landshut\nالرقم الضريبي: DE367619660",
                'de' => "Vertreten durch: Luay Rakik & Nasradin Albarho\nTelefon: +49 0871 / 6877271\nE-Mail: info@lookupfriseur.de\nHandelsregister: HRA 12712 – Amtsgericht Landshut\nUmsatzsteuer-ID: DE367619660",
                'en' => "Represented by: Luay Rakik & Nasradin Albarho\nPhone: +49 0871 / 6877271\nE-Mail: info@lookupfriseur.de\nTrade Register: HRA 12712 – Amtsgericht Landshut\nVAT ID: DE367619660",
            ]),

            $this->divider(),

            /* ─── Section 3: Data Collection ─────────────────── */
            $this->heading('h2', [
                'ar' => '٣. جمع ومعالجة البيانات الشخصية',
                'de' => '3. Erhebung und Verarbeitung personenbezogener Daten',
                'en' => '3. Collection and Processing of Personal Data',
            ]),

            // 3.1
            $this->titleParagraph(
                ['ar' => '٣.١ إنشاء الحساب',          'de' => '3.1 Kontoregistrierung',          'en' => '3.1 Account Registration'],
                [
                    'ar' => 'عند إنشاء حساب داخل التطبيق، قد يتم جمع البيانات التالية:',
                    'de' => 'Bei der Erstellung eines Benutzerkontos werden folgende personenbezogene Daten erhoben:',
                    'en' => 'When creating an account in the app, the following data may be collected:',
                ]
            ),
            $this->unorderedList([
                'ar' => ['الاسم الأول واسم العائلة', 'البريد الإلكتروني', 'رقم الهاتف', 'كلمة المرور (يتم حفظها بشكل مشفر)', 'صورة الملف الشخصي (اختياري)'],
                'de' => ['Vorname und Nachname', 'E-Mail-Adresse', 'Telefonnummer', 'Passwort (verschlüsselt gespeichert)', 'Profilbild (optional)'],
                'en' => ['First and last name', 'E-mail address', 'Phone number', 'Password (stored encrypted)', 'Profile picture (optional)'],
            ]),
            $this->paragraph([
                'ar' => 'الأساس القانوني: المادة 6 الفقرة 1 البند b من اللائحة الأوروبية لحماية البيانات (DSGVO)',
                'de' => 'Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung)',
                'en' => 'Legal basis: Art. 6(1)(b) GDPR (performance of a contract)',
            ]),

            // 3.2
            $this->titleParagraph(
                ['ar' => '٣.٢ تسجيل الدخول عبر Google', 'de' => '3.2 Anmeldung über Google',          'en' => '3.2 Sign-in via Google'],
                [
                    'ar' => 'يمكن للمستخدم تسجيل الدخول باستخدام حساب Google. قد يتم الحصول على البيانات التالية:',
                    'de' => 'Benutzer können sich optional über ihr Google-Konto anmelden. Dabei können folgende Daten übermittelt werden:',
                    'en' => 'Users may optionally sign in using their Google account. The following data may be retrieved from Google:',
                ]
            ),
            $this->unorderedList([
                'ar' => ['الاسم', 'البريد الإلكتروني', 'صورة الملف الشخصي'],
                'de' => ['Name', 'E-Mail-Adresse', 'Profilbild'],
                'en' => ['Name', 'E-mail address', 'Profile picture'],
            ]),
            $this->paragraph([
                'ar' => 'الأساس القانوني: المادة 6 الفقرة 1 البند b من DSGVO',
                'de' => 'Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO',
                'en' => 'Legal basis: Art. 6(1)(b) GDPR',
            ]),

            // 3.3
            $this->titleParagraph(
                ['ar' => '٣.٣ بيانات الحجز', 'de' => '3.3 Buchungsdaten', 'en' => '3.3 Booking Data'],
                [
                    'ar' => 'عند استخدام التطبيق لحجز المواعيد، تتم معالجة المعلومات التالية:',
                    'de' => 'Bei der Nutzung der App zur Terminbuchung werden folgende Informationen verarbeitet:',
                    'en' => 'When using the app to book appointments, the following information is processed:',
                ]
            ),
            $this->unorderedList([
                'ar' => ['نوع الخدمة المحجوزة', 'مقدم الخدمة / الحلاق', 'تاريخ ووقت الموعد', 'الملاحظات', 'رقم الهاتف', 'البريد الإلكتروني', 'صورة الملف الشخصي'],
                'de' => ['gebuchte Dienstleistung', 'ausgewählter Anbieter / Friseur', 'Datum und Uhrzeit des Termins', 'Notizen', 'Telefonnummer', 'E-Mail-Adresse', 'Profilbild'],
                'en' => ['Booked service', 'Selected provider / barber', 'Date and time of appointment', 'Notes', 'Phone number', 'E-mail address', 'Profile picture'],
            ]),
            $this->paragraph([
                'ar' => 'الأساس القانوني: المادة 6 الفقرة 1 البند b من DSGVO',
                'de' => 'Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO',
                'en' => 'Legal basis: Art. 6(1)(b) GDPR',
            ]),

            // 3.4
            $this->titleParagraph(
                ['ar' => '٣.٤ البيانات التقنية', 'de' => '3.4 Technische Informationen', 'en' => '3.4 Technical Information'],
                [
                    'ar' => 'قد يتم جمع بعض المعلومات التقنية تلقائيًا عند استخدام التطبيق، مثل:',
                    'de' => 'Beim Verwenden der App können automatisch technische Informationen verarbeitet werden:',
                    'en' => 'Some technical information may be collected automatically when you use the app, such as:',
                ]
            ),
            $this->unorderedList([
                'ar' => ['نوع الجهاز', 'نظام التشغيل', 'عنوان IP', 'تقارير الأعطال والأخطاء', 'بيانات الاستخدام التقنية'],
                'de' => ['Gerätetyp', 'Betriebssystem', 'IP-Adresse', 'Fehler- und Absturzprotokolle', 'technische Nutzungsdaten'],
                'en' => ['Device type', 'Operating system', 'IP address', 'Crash and error reports', 'Technical usage data'],
            ]),
            $this->paragraph([
                'ar' => 'الأساس القانوني: المادة 6 الفقرة 1 البند f من DSGVO (المصلحة المشروعة)',
                'de' => 'Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse)',
                'en' => 'Legal basis: Art. 6(1)(f) GDPR (legitimate interest)',
            ]),

            $this->divider(),

            /* ─── Section 4: Purposes ─────────────────────────── */
            $this->heading('h2', [
                'ar' => '٤. أهداف معالجة البيانات',
                'de' => '4. Zweck der Datenverarbeitung',
                'en' => '4. Purposes of Data Processing',
            ]),

            $this->paragraph([
                'ar' => 'تتم معالجة البيانات الشخصية للأغراض التالية:',
                'de' => 'Die Verarbeitung personenbezogener Daten erfolgt zu folgenden Zwecken:',
                'en' => 'Personal data is processed for the following purposes:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'إنشاء وإدارة حسابات المستخدمين',
                    'توفير خدمات الحجز',
                    'التواصل مع المستخدمين',
                    'إدارة المواعيد',
                    'إرسال الإشعارات',
                    'تحسين أداء التطبيق',
                    'تحليل الأخطاء والأمان',
                    'منع الاحتيال أو إساءة الاستخدام',
                    'تقديم الدعم الفني',
                ],
                'de' => [
                    'Erstellung und Verwaltung von Benutzerkonten',
                    'Bereitstellung der Buchungsfunktionen',
                    'Kommunikation mit Nutzern',
                    'Verwaltung von Terminen',
                    'Versand von Benachrichtigungen',
                    'Verbesserung der App-Leistung',
                    'Fehleranalyse und Sicherheit',
                    'Missbrauchs- und Betrugsprävention',
                    'Kundensupport',
                ],
                'en' => [
                    'Creating and managing user accounts',
                    'Providing booking services',
                    'Communicating with users',
                    'Managing appointments',
                    'Sending notifications',
                    'Improving app performance',
                    'Error analysis and security',
                    'Fraud and abuse prevention',
                    'Technical support',
                ],
            ]),

            $this->divider(),

            /* ─── Section 5: Push Notifications ──────────────── */
            $this->heading('h2', [
                'ar' => '٥. الإشعارات',
                'de' => '5. Push-Benachrichtigungen',
                'en' => '5. Push Notifications',
            ]),

            $this->paragraph([
                'ar' => 'يستخدم التطبيق خدمة OneSignal لإرسال الإشعارات. قد تشمل الإشعارات:',
                'de' => 'Die App verwendet OneSignal für Push-Benachrichtigungen. Benachrichtigungen können insbesondere enthalten:',
                'en' => 'The app uses OneSignal to send push notifications. Notifications may include:',
            ]),

            $this->unorderedList([
                'ar' => ['تأكيد الحجز', 'التذكير بالمواعيد', 'تحديثات تتعلق بالحجوزات'],
                'de' => ['Buchungsbestätigungen', 'Terminerinnerungen', 'Informationen zu Buchungen'],
                'en' => ['Booking confirmations', 'Appointment reminders', 'Updates related to bookings'],
            ]),

            $this->paragraph([
                'ar' => 'يمكن للمستخدم تعطيل الإشعارات في أي وقت من إعدادات الجهاز.',
                'de' => 'Benutzer können Push-Benachrichtigungen jederzeit über die Geräteeinstellungen deaktivieren.',
                'en' => 'Users can disable push notifications at any time through their device settings.',
            ]),

            $this->divider(),

            /* ─── Section 6: Firebase ─────────────────────────── */
            $this->heading('h2', [
                'ar' => '٦. Firebase Authentication',
                'de' => '6. Firebase Authentication',
                'en' => '6. Firebase Authentication',
            ]),

            $this->paragraph([
                'ar' => 'يستخدم التطبيق خدمة Firebase Authentication لتسجيل الدخول وإدارة الحسابات. قد تتم معالجة البيانات الشخصية وتخزينها على خوادم Google. مزيد من المعلومات: سياسة خصوصية Firebase.',
                'de' => 'Für die Benutzeranmeldung verwendet die App Firebase Authentication. Dabei können personenbezogene Daten verarbeitet und auf Servern von Google gespeichert werden. Weitere Informationen: Firebase Datenschutzrichtlinie.',
                'en' => 'The app uses Firebase Authentication for user login and account management. Personal data may be processed and stored on Google servers. For more information, see: Firebase Privacy Policy.',
            ]),

            $this->divider(),

            /* ─── Section 7: WhatsApp Support ────────────────── */
            $this->heading('h2', [
                'ar' => '٧. دعم WhatsApp',
                'de' => '7. WhatsApp-Support',
                'en' => '7. WhatsApp Support',
            ]),

            $this->paragraph([
                'ar' => 'إذا تواصل المستخدم مع الدعم عبر WhatsApp، فقد تتم معالجة بعض البيانات مثل رقم الهاتف ومحتوى الرسائل. استخدام WhatsApp اختياري. كما تنطبق سياسة الخصوصية الخاصة بـ WhatsApp.',
                'de' => 'Wenn Benutzer den Support über WhatsApp kontaktieren, können personenbezogene Daten wie Telefonnummer und Kommunikationsinhalte verarbeitet werden. Die Nutzung von WhatsApp erfolgt freiwillig. Es gelten zusätzlich die Datenschutzbestimmungen von WhatsApp.',
                'en' => 'If a user contacts support via WhatsApp, personal data such as phone number and message content may be processed. Use of WhatsApp is voluntary. WhatsApp\'s own privacy policy also applies.',
            ]),

            $this->divider(),

            /* ─── Section 8: Data Sharing ─────────────────────── */
            $this->heading('h2', [
                'ar' => '٨. مشاركة البيانات',
                'de' => '8. Weitergabe von Daten',
                'en' => '8. Data Sharing',
            ]),

            $this->paragraph([
                'ar' => 'لا نقوم ببيع أو تأجير البيانات الشخصية لأي طرف ثالث. قد تتم مشاركة البيانات فقط في الحالات التالية:',
                'de' => 'Personenbezogene Daten werden grundsätzlich nicht verkauft oder vermietet. Eine Weitergabe erfolgt ausschließlich:',
                'en' => 'We do not sell or rent personal data to third parties. Data may only be shared in the following cases:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'مع الحلاقين أو مقدمي الخدمة لتنفيذ الحجوزات',
                    'مع مزودي الخدمات التقنية (مثل خدمات الاستضافة أو الإشعارات)',
                    'إذا كان ذلك مطلوبًا قانونيًا',
                    'لحماية الحقوق القانونية',
                ],
                'de' => [
                    'an Friseure bzw. Anbieter zur Durchführung der gebuchten Dienstleistungen',
                    'an technische Dienstleister (z. B. Hosting- oder Benachrichtigungsdienste)',
                    'wenn dies gesetzlich vorgeschrieben ist',
                    'zur Durchsetzung rechtlicher Ansprüche',
                ],
                'en' => [
                    'With barbers or service providers to fulfill bookings',
                    'With technical service providers (e.g. hosting or notification services)',
                    'When required by law',
                    'To enforce legal claims',
                ],
            ]),

            $this->paragraph([
                'ar' => 'يمكن للحلاقين أو مقدمي الخدمة الاطلاع على:',
                'de' => 'Friseure können insbesondere folgende Daten einsehen:',
                'en' => 'Barbers and service providers may access the following data:',
            ]),

            $this->unorderedList([
                'ar' => ['الاسم', 'رقم الهاتف', 'البريد الإلكتروني', 'صورة الملف الشخصي', 'بيانات الموعد', 'الملاحظات'],
                'de' => ['Name', 'Telefonnummer', 'E-Mail-Adresse', 'Profilbild', 'Termininformationen', 'Notizen'],
                'en' => ['Name', 'Phone number', 'E-mail address', 'Profile picture', 'Appointment details', 'Notes'],
            ]),

            $this->divider(),

            /* ─── Section 9: Hosting ──────────────────────────── */
            $this->heading('h2', [
                'ar' => '٩. الاستضافة وموقع الخوادم',
                'de' => '9. Hosting und Serverstandort',
                'en' => '9. Hosting and Server Location',
            ]),

            $this->paragraph([
                'ar' => 'يتم استضافة التطبيق لدى مزود خدمات استضافة داخل ألمانيا أو الاتحاد الأوروبي. نطبق إجراءات تقنية وتنظيمية مناسبة لحماية البيانات الشخصية.',
                'de' => 'Die App wird bei einem externen Hosting-Anbieter innerhalb Deutschlands oder der Europäischen Union betrieben. Es werden angemessene technische und organisatorische Maßnahmen getroffen, um personenbezogene Daten zu schützen.',
                'en' => 'The app is hosted by a service provider located within Germany or the European Union. We implement appropriate technical and organizational measures to protect personal data.',
            ]),

            $this->divider(),

            /* ─── Section 10: International Transfers ─────────── */
            $this->heading('h2', [
                'ar' => '١٠. نقل البيانات إلى خارج الاتحاد الأوروبي',
                'de' => '10. Drittlandübermittlung',
                'en' => '10. International Data Transfers',
            ]),

            $this->paragraph([
                'ar' => 'قد تقوم بعض الخدمات المستخدمة بنقل البيانات الشخصية إلى خارج الاتحاد الأوروبي، وخاصة إلى الولايات المتحدة الأمريكية:',
                'de' => 'Einige eingesetzte Dienste können personenbezogene Daten außerhalb der Europäischen Union, insbesondere in die USA, übertragen:',
                'en' => 'Some services used may transfer personal data outside the European Union, particularly to the United States:',
            ]),

            $this->unorderedList([
                'ar' => ['Google', 'Firebase', 'OneSignal', 'WhatsApp'],
                'de' => ['Google', 'Firebase', 'OneSignal', 'WhatsApp'],
                'en' => ['Google', 'Firebase', 'OneSignal', 'WhatsApp'],
            ]),

            $this->paragraph([
                'ar' => 'ويتم ذلك وفق ضمانات قانونية مناسبة طبقًا للمادة 46 من DSGVO، مثل بنود العقود القياسية المعتمدة من الاتحاد الأوروبي.',
                'de' => 'Die Übermittlung erfolgt auf Grundlage geeigneter Garantien gemäß Art. 46 DSGVO, insbesondere EU-Standardvertragsklauseln.',
                'en' => 'Such transfers are carried out on the basis of appropriate safeguards pursuant to Art. 46 GDPR, in particular EU Standard Contractual Clauses.',
            ]),

            $this->divider(),

            /* ─── Section 11: Data Security ──────────────────── */
            $this->heading('h2', [
                'ar' => '١١. أمان البيانات',
                'de' => '11. Datensicherheit',
                'en' => '11. Data Security',
            ]),

            $this->paragraph([
                'ar' => 'نستخدم إجراءات أمنية مناسبة لحماية البيانات، ومنها:',
                'de' => 'Wir verwenden angemessene technische und organisatorische Sicherheitsmaßnahmen, insbesondere:',
                'en' => 'We use appropriate security measures to protect data, including:',
            ]),

            $this->unorderedList([
                'ar' => ['تشفير الاتصال (HTTPS/TLS)', 'تشفير كلمات المرور', 'تقييد الوصول للبيانات', 'حماية الخوادم وقواعد البيانات'],
                'de' => ['verschlüsselte Datenübertragung (HTTPS/TLS)', 'Passwortverschlüsselung', 'Zugriffsbeschränkungen', 'Schutz der Server und Datenbanken'],
                'en' => ['Encrypted data transmission (HTTPS/TLS)', 'Password encryption', 'Access restrictions', 'Server and database protection'],
            ]),

            $this->paragraph([
                'ar' => 'ورغم ذلك، لا يمكن ضمان أمان نقل البيانات عبر الإنترنت بشكل كامل.',
                'de' => 'Trotz aller Maßnahmen kann eine vollständige Sicherheit der Datenübertragung im Internet nicht garantiert werden.',
                'en' => 'Despite all measures, complete security of data transmission over the internet cannot be guaranteed.',
            ]),

            $this->divider(),

            /* ─── Section 12: Data Retention ─────────────────── */
            $this->heading('h2', [
                'ar' => '١٢. مدة الاحتفاظ بالبيانات',
                'de' => '12. Speicherdauer',
                'en' => '12. Data Retention',
            ]),

            $this->paragraph([
                'ar' => 'يتم الاحتفاظ بالبيانات الشخصية فقط للمدة اللازمة لتحقيق الأغراض المطلوبة. قد يتم الاحتفاظ ببيانات الحجوزات والفواتير لمدة تصل إلى 10 سنوات بسبب الالتزامات القانونية. بعد انتهاء الغرض من المعالجة أو انتهاء المدة القانونية، يتم حذف البيانات.',
                'de' => 'Personenbezogene Daten werden nur so lange gespeichert, wie dies für die jeweiligen Zwecke erforderlich ist. Buchungsdaten und Rechnungsinformationen können aufgrund gesetzlicher Aufbewahrungspflichten bis zu 10 Jahre gespeichert werden. Nach Wegfall des Verarbeitungszwecks oder Ablauf gesetzlicher Fristen werden die Daten gelöscht.',
                'en' => 'Personal data is retained only for as long as necessary to fulfill the stated purposes. Booking data and invoices may be retained for up to 10 years due to legal obligations. Once the processing purpose no longer applies or the legal retention period has expired, the data is deleted.',
            ]),

            $this->divider(),

            /* ─── Section 13: Account Deletion ───────────────── */
            $this->heading('h2', [
                'ar' => '١٣. حذف الحساب',
                'de' => '13. Konto-Löschung',
                'en' => '13. Account Deletion',
            ]),

            $this->paragraph([
                'ar' => 'يمكن للمستخدم حذف حسابه في أي وقت من داخل التطبيق. بعد الحذف، يتم حذف البيانات الشخصية ما لم توجد التزامات قانونية تمنع ذلك. قد يتم الاحتفاظ ببعض البيانات مثل بيانات الفواتير والحجوزات وفقًا للمتطلبات القانونية.',
                'de' => 'Benutzer können ihr Konto jederzeit innerhalb der App löschen. Nach der Löschung werden personenbezogene Daten grundsätzlich entfernt, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen. Bestimmte Daten, insbesondere Rechnungs- und Buchungsdaten, können aufgrund gesetzlicher Vorgaben weiterhin gespeichert bleiben.',
                'en' => 'Users can delete their account at any time from within the app. After deletion, personal data will generally be removed unless legal obligations prevent this. Certain data, such as billing and booking records, may be retained in accordance with legal requirements.',
            ]),

            $this->divider(),

            /* ─── Section 14: User Rights ─────────────────────── */
            $this->heading('h2', [
                'ar' => '١٤. حقوق المستخدم',
                'de' => '14. Rechte der Benutzer',
                'en' => '14. User Rights',
            ]),

            $this->paragraph([
                'ar' => 'يحق للمستخدم وفقًا لـ DSGVO:',
                'de' => 'Benutzer haben nach der DSGVO insbesondere folgende Rechte:',
                'en' => 'Under the GDPR, users have the following rights:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'الحصول على نسخة من بياناته',
                    'تصحيح البيانات',
                    'حذف البيانات',
                    'تقييد المعالجة',
                    'نقل البيانات',
                    'الاعتراض على المعالجة',
                    'سحب الموافقة في أي وقت',
                    'تقديم شكوى إلى جهة حماية البيانات المختصة',
                ],
                'de' => [
                    'Recht auf Auskunft',
                    'Recht auf Berichtigung',
                    'Recht auf Löschung',
                    'Recht auf Einschränkung der Verarbeitung',
                    'Recht auf Datenübertragbarkeit',
                    'Recht auf Widerspruch gegen die Verarbeitung',
                    'Recht auf Widerruf erteilter Einwilligungen',
                    'Recht auf Beschwerde bei einer Datenschutzaufsichtsbehörde',
                ],
                'en' => [
                    'Right of access to personal data',
                    'Right to rectification',
                    'Right to erasure ("right to be forgotten")',
                    'Right to restriction of processing',
                    'Right to data portability',
                    'Right to object to processing',
                    'Right to withdraw consent at any time',
                    'Right to lodge a complaint with a supervisory authority',
                ],
            ]),

            $this->divider(),

            /* ─── Section 15: Minimum Age ─────────────────────── */
            $this->heading('h2', [
                'ar' => '١٥. الحد الأدنى للعمر',
                'de' => '15. Mindestalter',
                'en' => '15. Minimum Age',
            ]),

            $this->paragraph([
                'ar' => 'يسمح باستخدام التطبيق فقط للأشخاص الذين تبلغ أعمارهم 16 عامًا أو أكثر. لا نقوم بجمع بيانات شخصية لأطفال دون 16 عامًا عن علم.',
                'de' => 'Die Nutzung der App ist ausschließlich Personen ab 16 Jahren gestattet. Wir erfassen wissentlich keine personenbezogenen Daten von Kindern unter 16 Jahren.',
                'en' => 'The app may only be used by persons aged 16 or older. We do not knowingly collect personal data from children under the age of 16.',
            ]),

            $this->divider(),

            /* ─── Section 16: Policy Changes ─────────────────── */
            $this->heading('h2', [
                'ar' => '١٦. التعديلات على سياسة الخصوصية',
                'de' => '16. Änderungen dieser Datenschutzrichtlinie',
                'en' => '16. Changes to This Privacy Policy',
            ]),

            $this->paragraph([
                'ar' => 'نحتفظ بالحق في تعديل سياسة الخصوصية في أي وقت لتتوافق مع المتطلبات القانونية أو تحديثات التطبيق. تتوفر النسخة الحالية دائمًا داخل التطبيق.',
                'de' => 'Wir behalten uns vor, diese Datenschutzrichtlinie jederzeit anzupassen, um sie an rechtliche Anforderungen oder Änderungen der App anzupassen. Die jeweils aktuelle Version ist innerhalb der App verfügbar.',
                'en' => 'We reserve the right to update this Privacy Policy at any time to reflect legal requirements or changes to the app. The current version is always available within the app.',
            ]),

            $this->divider(),

            /* ─── Section 17: Contact ─────────────────────────── */
            $this->heading('h2', [
                'ar' => '١٧. التواصل معنا',
                'de' => '17. Kontakt',
                'en' => '17. Contact',
            ]),

            $this->paragraph([
                'ar' => "لأي استفسارات تتعلق بحماية البيانات أو سياسة الخصوصية، يمكن التواصل معنا عبر:\n\nالبريد الإلكتروني: info@lookupfriseur.de\nالهاتف: +49 0871 / 6877271",
                'de' => "Bei Fragen zum Datenschutz oder zur Verarbeitung personenbezogener Daten können Sie uns kontaktieren:\n\nE-Mail: info@lookupfriseur.de\nTelefon: +49 0871 / 6877271",
                'en' => "For any questions about data protection or the processing of personal data, please contact us:\n\nE-mail: info@lookupfriseur.de\nPhone: +49 0871 / 6877271",
            ]),

            $this->divider(),

            /* ─── Footer: Last Updated ────────────────────────── */
            $this->paragraph([
                'ar' => 'آخر تحديث: مايو ٢٠٢٦',
                'de' => 'Letzte Aktualisierung: Mai 2026',
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

    private function titleParagraph(array $titles, array $bodies, string $alignment = 'auto'): array
    {
        return [
            'type'         => 'title_paragraph',
            'is_active'    => true,
            'props'        => ['alignment' => $alignment, 'color' => 'default'],
            'translations' => [
                'ar' => ['title' => $titles['ar'] ?? '', 'text' => $bodies['ar'] ?? ''],
                'en' => ['title' => $titles['en'] ?? '', 'text' => $bodies['en'] ?? ''],
                'de' => ['title' => $titles['de'] ?? '', 'text' => $bodies['de'] ?? ''],
            ],
        ];
    }

    /**
     * @param  array<string, string[]>  $itemsPerLang  e.g. ['ar' => ['item1', 'item2'], ...]
     */
    private function unorderedList(array $itemsPerLang, string $alignment = 'auto'): array
    {
        $translations = [];

        foreach ($itemsPerLang as $lang => $items) {
            $translations[$lang] = [
                'items' => array_map(fn (string $item) => ['value' => $item], $items),
            ];
        }

        return [
            'type'         => 'unordered_list',
            'is_active'    => true,
            'props'        => ['alignment' => $alignment],
            'translations' => $translations,
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
