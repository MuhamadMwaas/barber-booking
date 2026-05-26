<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Seeder for the Terms & Conditions page
 * (شروط الاستخدام / Nutzungsbedingungen AGB).
 *
 * Run with:
 *   php artisan db:seed --class=TermsConditionsPageSeeder
 */
class TermsConditionsPageSeeder extends Seeder
{
    public function run(): void
    {
        CmsPage::where('slug', 'terms-conditions')->delete();

        CmsPage::create([
            'name'      => 'شروط الاستخدام',
            'slug'      => 'terms-conditions',
            'is_active' => true,
            'blocks'    => $this->blocks(),
        ]);

        $this->command->info('✅  Terms & Conditions page seeded.');
    }

    /* ══════════════════════════════════════════════════════════
     │  Block definitions
     ══════════════════════════════════════════════════════════ */

    private function blocks(): array
    {
        return [

            /* ─── Main title ─────────────────────────────────── */
            $this->heading('h1', [
                'ar' => 'الشروط والأحكام (AGB)',
                'de' => 'Nutzungsbedingungen (AGB)',
                'en' => 'Terms and Conditions (AGB)',
            ], 'auto', 'primary'),

            /* ─── Intro ──────────────────────────────────────── */
            $this->paragraph([
                'ar' => 'مرحبًا بك في تطبيق „Look Up".',
                'de' => 'Willkommen bei der App „Look Up".',
                'en' => 'Welcome to the „Look Up" app.',
            ]),

            $this->paragraph([
                'ar' => 'تنظم الشروط والأحكام التالية استخدام تطبيق „Look Up" الخاص بحجز مواعيد خدمات الحلاقة.',
                'de' => 'Die nachfolgenden Nutzungsbedingungen regeln die Nutzung der mobilen Anwendung „Look Up" zur Terminbuchung bei Friseurdienstleistungen.',
                'en' => 'The following terms and conditions govern the use of the mobile application „Look Up" for booking barbershop appointments.',
            ]),

            $this->paragraph([
                'ar' => 'من خلال التسجيل أو استخدام التطبيق، فإنك توافق على هذه الشروط والأحكام.',
                'de' => 'Mit der Registrierung oder Nutzung der App erklären Sie sich mit diesen Nutzungsbedingungen einverstanden.',
                'en' => 'By registering or using the app, you agree to these Terms and Conditions.',
            ]),

            $this->divider(),

            /* ─── Section 1: App Provider ─────────────────────── */
            $this->heading('h2', [
                'ar' => '١. مزود التطبيق',
                'de' => '1. Anbieter der App',
                'en' => '1. App Provider',
            ]),

            $this->paragraph([
                'ar' => "مزود التطبيق هو:\n\nLook up OHG\nRupprechtstraße 33\n84034 Landshut\nDeutschland",
                'de' => "Anbieter der App ist:\n\nLook up OHG\nRupprechtstraße 33\n84034 Landshut\nDeutschland",
                'en' => "The app is provided by:\n\nLook up OHG\nRupprechtstraße 33\n84034 Landshut\nGermany",
            ]),

            $this->paragraph([
                'ar' => "السجل التجاري: HRA 12712\nالمحكمة المختصة: Amtsgericht Landshut\nالهاتف: +49 0871 / 6877271\nالبريد الإلكتروني: info@lookupfriseur.de\nالشركاء المخولون بالتمثيل: Luay Rakik & Nasradin Albarho",
                'de' => "Handelsregister: HRA 12712\nRegistergericht: Amtsgericht Landshut\nTelefon: +49 0871 / 6877271\nE-Mail: info@lookupfriseur.de\nVertretungsberechtigte Gesellschafter: Luay Rakik & Nasradin Albarho",
                'en' => "Trade Register: HRA 12712\nRegistry Court: Amtsgericht Landshut\nPhone: +49 0871 / 6877271\nE-Mail: info@lookupfriseur.de\nAuthorized representatives: Luay Rakik & Nasradin Albarho",
            ]),

            $this->divider(),

            /* ─── Section 2: Purpose of the App ─────────────── */
            $this->heading('h2', [
                'ar' => '٢. موضوع التطبيق',
                'de' => '2. Gegenstand der App',
                'en' => '2. Purpose of the App',
            ]),

            $this->paragraph([
                'ar' => 'يُستخدم تطبيق „Look Up" حصريًا لتنظيم وإدارة مواعيد الحلاقة بين العملاء وصالونات الحلاقة.',
                'de' => 'Die App „Look Up" dient ausschließlich der Vermittlung und Verwaltung von Friseurterminen zwischen Kunden und dem jeweiligen Friseursalon.',
                'en' => 'The „Look Up" app is used exclusively to organise and manage barbershop appointments between customers and salons.',
            ]),

            $this->paragraph([
                'ar' => 'التطبيق نفسه لا يقدم خدمات الحلاقة.',
                'de' => 'Die App selbst erbringt keine Friseurdienstleistungen.',
                'en' => 'The app itself does not provide any barbershop services.',
            ]),

            $this->paragraph([
                'ar' => 'يتم إبرام عقد الخدمة مباشرة بين العميل وصالون الحلاقة المعني.',
                'de' => 'Der Vertrag über die jeweilige Dienstleistung kommt ausschließlich zwischen dem Kunden und dem Friseursalon zustande.',
                'en' => 'The service contract is concluded directly between the customer and the respective barbershop.',
            ]),

            $this->divider(),

            /* ─── Section 3: Registration & Account ──────────── */
            $this->heading('h2', [
                'ar' => '٣. التسجيل وحساب المستخدم',
                'de' => '3. Registrierung und Nutzerkonto',
                'en' => '3. Registration and User Account',
            ]),

            $this->paragraph([
                'ar' => 'يتطلب استخدام بعض وظائف التطبيق إنشاء حساب مستخدم. يلتزم المستخدم بما يلي:',
                'de' => 'Für die Nutzung bestimmter Funktionen der App ist eine Registrierung erforderlich. Der Nutzer verpflichtet sich:',
                'en' => 'Using certain features of the app requires creating a user account. The user agrees to:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'تقديم معلومات صحيحة وكاملة عند التسجيل',
                    'الحفاظ على سرية بيانات تسجيل الدخول',
                    'عدم استخدام حسابات تخص أشخاص آخرين',
                    'عدم استخدام التطبيق بشكل مخالف للقانون أو بشكل مسيء',
                ],
                'de' => [
                    'bei der Registrierung wahrheitsgemäße und vollständige Angaben zu machen',
                    'seine Zugangsdaten vertraulich zu behandeln',
                    'keine fremden Konten zu verwenden',
                    'die App nicht missbräuchlich oder rechtswidrig zu nutzen',
                ],
                'en' => [
                    'Providing accurate and complete information upon registration',
                    'Keeping login credentials confidential',
                    'Not using accounts belonging to other persons',
                    'Not using the app in an unlawful or abusive manner',
                ],
            ]),

            $this->paragraph([
                'ar' => 'ويتحمل المستخدم المسؤولية الكاملة عن جميع الأنشطة التي تتم من خلال حسابه.',
                'de' => 'Der Nutzer ist für sämtliche Aktivitäten verantwortlich, die über sein Benutzerkonto erfolgen.',
                'en' => 'The user bears full responsibility for all activities carried out through their account.',
            ]),

            $this->divider(),

            /* ─── Section 4: Minimum Age ──────────────────────── */
            $this->heading('h2', [
                'ar' => '٤. الحد الأدنى للعمر',
                'de' => '4. Mindestalter',
                'en' => '4. Minimum Age',
            ]),

            $this->warningBox([
                'ar' => 'يسمح باستخدام التطبيق فقط للأشخاص الذين أتموا 16 عامًا من العمر. وبإتمام عملية التسجيل، يؤكد المستخدم أنه بلغ الحد الأدنى المطلوب للعمر.',
                'de' => 'Die Nutzung der App ist nur Personen gestattet, die das 16. Lebensjahr vollendet haben. Mit der Registrierung bestätigt der Nutzer, das erforderliche Mindestalter erreicht zu haben.',
                'en' => 'The app may only be used by persons who have reached the age of 16. By completing the registration, the user confirms that they have reached the required minimum age.',
            ]),

            $this->divider(),

            /* ─── Section 5: Bookings ─────────────────────────── */
            $this->heading('h2', [
                'ar' => '٥. الحجوزات',
                'de' => '5. Buchungen',
                'en' => '5. Bookings',
            ]),

            $this->paragraph([
                'ar' => 'يمكن من خلال التطبيق حجز مواعيد لخدمات الحلاقة. يلتزم المستخدم بما يلي:',
                'de' => 'Über die App können Termine für Friseurdienstleistungen gebucht werden. Der Nutzer verpflichtet sich:',
                'en' => 'The app enables users to book appointments for barbershop services. The user agrees to:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'الالتزام بالمواعيد المحجوزة',
                    'الحضور في الوقت المحدد',
                    'إلغاء الموعد في أقرب وقت ممكن عند التعذر',
                ],
                'de' => [
                    'gebuchte Termine einzuhalten',
                    'pünktlich zum vereinbarten Termin zu erscheinen',
                    'bei Verhinderung den Termin möglichst frühzeitig zu stornieren',
                ],
                'en' => [
                    'Honouring booked appointments',
                    'Arriving on time for the scheduled appointment',
                    'Cancelling the appointment as early as possible if unable to attend',
                ],
            ]),

            $this->paragraph([
                'ar' => 'يُنصح بإلغاء الموعد قبل ساعة واحدة على الأقل من موعد الحجز.',
                'de' => 'Es wird empfohlen, Terminabsagen mindestens eine Stunde vor dem vereinbarten Termin vorzunehmen.',
                'en' => 'It is recommended to cancel appointments at least one hour before the scheduled time.',
            ]),

            $this->paragraph([
                'ar' => 'ويحتفظ صالون الحلاقة بحق رفض الموعد أو تأجيله في حالة التأخر الكبير.',
                'de' => 'Der Friseursalon behält sich das Recht vor, Termine bei erheblicher Verspätung abzulehnen oder zu verschieben.',
                'en' => 'The barbershop reserves the right to refuse or reschedule appointments in the event of significant delay.',
            ]),

            $this->divider(),

            /* ─── Section 6: Prices & Payment ────────────────── */
            $this->heading('h2', [
                'ar' => '٦. الأسعار والدفع',
                'de' => '6. Preise und Zahlung',
                'en' => '6. Prices and Payment',
            ]),

            $this->paragraph([
                'ar' => 'يتم الدفع مباشرة في صالون الحلاقة فقط.',
                'de' => 'Die Zahlung erfolgt ausschließlich direkt vor Ort beim Friseursalon.',
                'en' => 'Payment is made exclusively on-site at the barbershop.',
            ]),

            $this->paragraph([
                'ar' => 'التطبيق لا يعالج أي مدفوعات إلكترونية.',
                'de' => 'Die App selbst verarbeitet keine Online-Zahlungen.',
                'en' => 'The app itself does not process any online payments.',
            ]),

            $this->paragraph([
                'ar' => 'وقد تختلف طرق الدفع حسب الصالون، سواء نقدًا أو بواسطة البطاقة.',
                'de' => 'Je nach Friseursalon kann die Zahlung in bar oder per Karte erfolgen.',
                'en' => 'Payment methods may vary by salon and include cash or card.',
            ]),

            $this->divider(),

            /* ─── Section 7: Cancellations ───────────────────── */
            $this->heading('h2', [
                'ar' => '٧. إلغاء المواعيد',
                'de' => '7. Stornierungen',
                'en' => '7. Cancellations',
            ]),

            $this->paragraph([
                'ar' => 'يمكن للمستخدم إلغاء المواعيد المحجوزة في أي وقت عبر التطبيق.',
                'de' => 'Gebuchte Termine können vom Nutzer jederzeit über die App storniert werden.',
                'en' => 'Users may cancel booked appointments at any time through the app.',
            ]),

            $this->paragraph([
                'ar' => 'كما يحتفظ التطبيق أو صالون الحلاقة بحق تأجيل أو إلغاء المواعيد لأسباب تنظيمية أو تقنية.',
                'de' => 'Die App oder der Friseursalon behalten sich das Recht vor, Termine aus organisatorischen oder technischen Gründen zu verschieben oder abzusagen.',
                'en' => 'The app or the barbershop reserves the right to reschedule or cancel appointments for organisational or technical reasons.',
            ]),

            $this->divider(),

            /* ─── Section 8: User Obligations ────────────────── */
            $this->heading('h2', [
                'ar' => '٨. التزامات المستخدمين',
                'de' => '8. Pflichten der Nutzer',
                'en' => '8. User Obligations',
            ]),

            $this->paragraph([
                'ar' => 'يُمنع على المستخدمين بشكل خاص:',
                'de' => 'Den Nutzern ist insbesondere untersagt:',
                'en' => 'Users are specifically prohibited from:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'إجراء حجوزات وهمية',
                    'التلاعب التقني بالتطبيق',
                    'تجاوز أنظمة الحماية',
                    'نشر برمجيات ضارة أو محتوى ضار',
                    'إزعاج أو إهانة المستخدمين الآخرين أو صالونات الحلاقة',
                ],
                'de' => [
                    'falsche Buchungen vorzunehmen',
                    'die App technisch zu manipulieren',
                    'Sicherheitsmechanismen zu umgehen',
                    'Schadsoftware oder schädliche Inhalte zu verbreiten',
                    'andere Nutzer oder den Friseursalon zu belästigen oder zu beleidigen',
                ],
                'en' => [
                    'Making false or fictitious bookings',
                    'Technically manipulating the app',
                    'Circumventing security mechanisms',
                    'Distributing malware or harmful content',
                    'Harassing or insulting other users or barbershops',
                ],
            ]),

            $this->divider(),

            /* ─── Section 9: App Availability ────────────────── */
            $this->heading('h2', [
                'ar' => '٩. توفر التطبيق',
                'de' => '9. Verfügbarkeit der App',
                'en' => '9. App Availability',
            ]),

            $this->paragraph([
                'ar' => 'يسعى مزودو التطبيق إلى توفير التطبيق بأكبر قدر ممكن دون انقطاع.',
                'de' => 'Die Anbieter bemühen sich um eine möglichst unterbrechungsfreie Verfügbarkeit der App.',
                'en' => 'The app providers strive to make the app available with as little interruption as possible.',
            ]),

            $this->paragraph([
                'ar' => 'ومع ذلك، لا يمكن ضمان توفر التطبيق بشكل دائم.',
                'de' => 'Eine jederzeitige Verfügbarkeit kann jedoch nicht garantiert werden.',
                'en' => 'However, uninterrupted availability cannot be guaranteed at all times.',
            ]),

            $this->paragraph([
                'ar' => 'قد تحدث قيود مؤقتة بسبب أعمال الصيانة أو الأعطال التقنية أو الظروف الخارجة عن الإرادة.',
                'de' => 'Insbesondere können Wartungsarbeiten, technische Störungen oder höhere Gewalt zu Einschränkungen führen.',
                'en' => 'Temporary restrictions may occur due to maintenance work, technical faults, or circumstances beyond our control.',
            ]),

            $this->divider(),

            /* ─── Section 10: Liability ───────────────────────── */
            $this->heading('h2', [
                'ar' => '١٠. المسؤولية',
                'de' => '10. Haftung',
                'en' => '10. Liability',
            ]),

            $this->paragraph([
                'ar' => 'يعمل التطبيق فقط كمنصة وسيطة لحجز المواعيد. وتقع مسؤولية تنفيذ وجودة ونتائج خدمات الحلاقة بالكامل على صالون الحلاقة المعني.',
                'de' => 'Die App dient ausschließlich als Vermittlungsplattform für Terminbuchungen. Für die Durchführung, Qualität oder Ergebnisse der Friseurdienstleistungen ist ausschließlich der jeweilige Friseursalon verantwortlich.',
                'en' => 'The app operates solely as an intermediary platform for booking appointments. Responsibility for the performance, quality and results of barbershop services lies exclusively with the respective barbershop.',
            ]),

            $this->paragraph([
                'ar' => 'لا تتحمل Look up OHG المسؤولية عن:',
                'de' => 'Die Look up OHG haftet nicht für:',
                'en' => 'Look up OHG is not liable for:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'تأجيل أو إلغاء المواعيد',
                    'جودة الخدمات المقدمة',
                    'فقدان البيانات أو الأعطال التقنية',
                    'الأضرار الناتجة عن الاستخدام غير الصحيح للتطبيق',
                ],
                'de' => [
                    'Terminverschiebungen oder Terminabsagen',
                    'Qualität der erbrachten Dienstleistungen',
                    'Datenverluste oder technische Ausfälle',
                    'Schäden, die durch unsachgemäße Nutzung der App entstehen',
                ],
                'en' => [
                    'Appointment rescheduling or cancellations',
                    'Quality of services rendered',
                    'Data loss or technical failures',
                    'Damages resulting from improper use of the app',
                ],
            ]),

            $this->paragraph([
                'ar' => 'ولا يؤثر ذلك على المسؤولية القانونية في حالات التعمد أو الإهمال الجسيم.',
                'de' => 'Die Haftung für Vorsatz und grobe Fahrlässigkeit bleibt unberührt.',
                'en' => 'Liability for intent and gross negligence remains unaffected.',
            ]),

            $this->divider(),

            /* ─── Section 11: Account Suspension ────────────── */
            $this->heading('h2', [
                'ar' => '١١. حظر أو حذف الحسابات',
                'de' => '11. Sperrung und Kündigung von Konten',
                'en' => '11. Account Suspension and Termination',
            ]),

            $this->paragraph([
                'ar' => 'يحتفظ مزودو التطبيق بالحق في تقييد أو إيقاف أو حذف حسابات المستخدمين بشكل مؤقت أو دائم في الحالات التالية:',
                'de' => 'Die Anbieter behalten sich das Recht vor, Nutzerkonten vorübergehend zu sperren, einzuschränken oder dauerhaft zu löschen, wenn:',
                'en' => 'The app providers reserve the right to temporarily or permanently restrict, suspend, or delete user accounts in the following cases:',
            ]),

            $this->unorderedList([
                'ar' => [
                    'مخالفة هذه الشروط والأحكام',
                    'إساءة استخدام التطبيق',
                    'تقديم معلومات غير صحيحة',
                    'تعريض أمن أو استقرار التطبيق للخطر',
                    'التغيب المتكرر عن المواعيد المحجوزة دون إلغاء مسبق',
                ],
                'de' => [
                    'gegen diese Nutzungsbedingungen verstoßen wird',
                    'missbräuchliche Nutzung festgestellt wird',
                    'falsche Angaben gemacht werden',
                    'die Sicherheit oder Stabilität der App gefährdet wird',
                    'wiederholt gebuchte Termine ohne rechtzeitige Absage nicht wahrgenommen werden',
                ],
                'en' => [
                    'Violation of these Terms and Conditions',
                    'Abusive use of the app',
                    'Provision of false information',
                    'Endangering the security or stability of the app',
                    'Repeated no-shows without prior cancellation',
                ],
            ]),

            $this->paragraph([
                'ar' => 'ويمكن بشكل خاص تعطيل حساب المستخدم إذا أدى التغيب المتكرر عن المواعيد إلى الإضرار بنظام الحجز أو بسير العمل في صالون الحلاقة.',
                'de' => 'Insbesondere kann ein Nutzerkonto deaktiviert werden, wenn durch wiederholtes Nichterscheinen bei gebuchten Terminen der ordnungsgemäße Ablauf des Buchungssystems oder der Geschäftsbetrieb des Friseursalons beeinträchtigt wird.',
                'en' => 'In particular, a user account may be deactivated if repeated no-shows disrupt the proper operation of the booking system or the business operations of the barbershop.',
            ]),

            $this->paragraph([
                'ar' => 'كما يمكن للمستخدم حذف حسابه في أي وقت.',
                'de' => 'Der Nutzer kann sein Konto jederzeit löschen lassen.',
                'en' => 'The user may also delete their account at any time.',
            ]),

            $this->divider(),

            /* ─── Section 12: Data Protection ────────────────── */
            $this->heading('h2', [
                'ar' => '١٢. حماية البيانات',
                'de' => '12. Datenschutz',
                'en' => '12. Data Protection',
            ]),

            $this->paragraph([
                'ar' => 'يمكن الاطلاع على المعلومات المتعلقة بمعالجة البيانات الشخصية في سياسة الخصوصية المنفصلة الخاصة بالتطبيق.',
                'de' => 'Informationen zur Verarbeitung personenbezogener Daten finden Sie in der separaten Datenschutzerklärung der App.',
                'en' => 'Information regarding the processing of personal data can be found in the separate Privacy Policy of the app.',
            ]),

            $this->divider(),

            /* ─── Section 13: Changes to Terms ───────────────── */
            $this->heading('h2', [
                'ar' => '١٣. تعديل الشروط والأحكام',
                'de' => '13. Änderungen der Nutzungsbedingungen',
                'en' => '13. Changes to These Terms',
            ]),

            $this->paragraph([
                'ar' => 'يحتفظ مزودو التطبيق بالحق في تعديل هذه الشروط والأحكام في أي وقت مع سريانها للمستقبل.',
                'de' => 'Die Anbieter behalten sich das Recht vor, diese Nutzungsbedingungen jederzeit mit Wirkung für die Zukunft anzupassen.',
                'en' => 'The app providers reserve the right to amend these Terms and Conditions at any time with future effect.',
            ]),

            $this->paragraph([
                'ar' => 'وسيتم إعلام المستخدمين بالتعديلات الجوهرية داخل التطبيق.',
                'de' => 'Über wesentliche Änderungen werden die Nutzer innerhalb der App informiert.',
                'en' => 'Users will be notified of material changes within the app.',
            ]),

            $this->divider(),

            /* ─── Section 14: Governing Law ───────────────────── */
            $this->heading('h2', [
                'ar' => '١٤. القانون المعمول به',
                'de' => '14. Anwendbares Recht',
                'en' => '14. Governing Law',
            ]),

            $this->paragraph([
                'ar' => 'يخضع استخدام التطبيق لقوانين جمهورية ألمانيا الاتحادية.',
                'de' => 'Es gilt das Recht der Bundesrepublik Deutschland.',
                'en' => 'The use of the app is governed by the laws of the Federal Republic of Germany.',
            ]),

            $this->paragraph([
                'ar' => 'وإذا كان ذلك مسموحًا قانونيًا، يكون مقر Look up OHG هو مكان الاختصاص القضائي.',
                'de' => 'Sofern gesetzlich zulässig, ist Gerichtsstand der Sitz der Look up OHG.',
                'en' => 'To the extent permitted by law, the registered office of Look up OHG shall be the place of jurisdiction.',
            ]),

            $this->divider(),

            /* ─── Section 15: Severability ────────────────────── */
            $this->heading('h2', [
                'ar' => '١٥. البند الختامي',
                'de' => '15. Salvatorische Klausel',
                'en' => '15. Severability Clause',
            ]),

            $this->paragraph([
                'ar' => 'إذا أصبحت بعض أحكام هذه الشروط والأحكام غير صالحة كليًا أو جزئيًا، فإن ذلك لا يؤثر على صلاحية بقية الأحكام الأخرى.',
                'de' => 'Sollten einzelne Bestimmungen dieser Nutzungsbedingungen ganz oder teilweise unwirksam sein oder werden, bleibt die Wirksamkeit der übrigen Bestimmungen unberührt.',
                'en' => 'Should any provision of these Terms and Conditions be or become wholly or partially invalid, the validity of the remaining provisions shall not be affected.',
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

    /** @param array<string, string[]> $itemsPerLang */
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
