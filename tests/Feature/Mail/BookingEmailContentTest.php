<?php

/**
 * Wording, language and audience of the customer booking emails:
 * the confirmation after an online booking and the reminder email.
 */

use App\Enum\BookingSource;
use App\Jobs\SendAppointmentReminderJob;
use App\Mail\AppointmentReminderMail;
use App\Mail\BookingConfirmationMail;
use App\Mail\BookingNotificationMail;
use App\Models\AppointmentReminder;
use App\Models\AppointmentService;
use App\Models\LandingSection;
use App\Models\Language;
use App\Models\UserSetting;
use App\Services\AppointmentReminderService;
use App\Services\BookingMailService;
use App\Services\SmsService;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SalonFixture;

beforeEach(function () {
    config(['app.name' => 'LookUp']);

    $this->salon = new SalonFixture;
    $this->appointment = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $this->appointment->update([
        'customer_id' => $this->salon->customer->id,
        'payment_method' => 'cash',
        'booking_source' => BookingSource::ONLINE,
        'notes' => 'Bitte kurz',
    ]);
    $this->salon->customer->update(['email' => 'lina.hassan@gmail.com']);
    $this->appointment->refresh();
});

afterEach(function () {
    Carbon::setTestNow();
});

function confirmationIn(string $locale): string
{
    return (new BookingConfirmationMail(test()->appointment, 'LookUp', '€', $locale))->render();
}

it('renders the German confirmation with the requested wording', function () {
    $html = confirmationIn('de');
    $name = test()->appointment->customer_name;

    expect($html)
        ->toContain('Buchungsbestätigung — ' . test()->appointment->number)
        ->toContain("Hallo {$name},")
        ->toContain('Vielen Dank für Ihre Buchung bei LookUp. Ihre Buchung wurde erfolgreich entgegengenommen.')
        ->toContain('Ihre Buchungsdetails')
        ->toContain('Buchungsnummer')
        ->toContain('Uhrzeit')
        ->toContain('09/09/2026')                       // German date, not "Sep 09, 2026"
        ->toContain('<span dir="ltr">10:00</span>')   // 24h clock, start only
        ->not->toContain('11:00')                       // the end time would reveal the duration
        ->toContain('Mitarbeiter/in')
        // The salon asked for no payment method in any email.
        ->not->toContain('Zahlungsmethode')
        ->not->toContain('Barzahlung')
        ->toContain('Hinweise')
        ->toContain('Ihre Leistungen')
        ->toContain('Leistung')
        // The salon asked for no duration and no prices in any email.
        ->not->toContain('Dauer')
        ->not->toContain('Preis')
        ->not->toContain('Zahlungsübersicht')
        ->not->toContain('Zwischensumme')
        ->not->toContain('€')
        ->toContain('Vielen Dank, dass Sie sich für LookUp entschieden haben! Wir freuen uns auf Ihren Besuch.')
        ->toContain('Dies ist eine automatisch versendete E-Mail. Bitte antworten Sie nicht direkt auf diese Nachricht.')
        ->not->toContain('>cash<');
});

it('renders the Arabic confirmation right-to-left with the requested wording', function () {
    $html = confirmationIn('ar');

    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('تأكيد الحجز — ' . test()->appointment->number)
        ->toContain('شكراً لحجزك لدى LookUp. تم استلام حجزك بنجاح.')
        ->toContain('تفاصيل حجزك')
        ->toContain('رقم الحجز')
        ->toContain('مقدم الخدمة')
        ->not->toContain('طريقة الدفع')
        ->not->toContain('نقداً')
        ->toContain('الملاحظات')
        ->toContain('الخدمات التي اخترتها')
        ->not->toContain('المدة')
        ->not->toContain('السعر')
        ->not->toContain('ملخص الدفع')
        ->toContain('شكراً لاختيارك LookUp! نتطلع إلى زيارتك.')
        ->toContain('هذه رسالة بريد إلكتروني مرسلة تلقائياً. يرجى عدم الرد مباشرةً على هذه الرسالة.');
});

// The booking runs 10:00–11:00; only the start may appear, in the email's own clock.
it('leaves the payment method and the duration out of the confirmation', function (string $locale, string $start, string $end) {
    $html = confirmationIn($locale);

    expect($html)
        ->toContain('<span dir="ltr">' . $start . '</span>')
        ->not->toContain($end)
        ->not->toContain(__('booking_email.payment_method', [], $locale))
        ->not->toContain(__('booking_email.payment_methods.cash', [], $locale))
        ->not->toContain(__('booking_email.duration', [], $locale));
})->with([
    'de' => ['de', '10:00', '11:00'],
    'en' => ['en', '10:00 AM', '11:00 AM'],
    'ar' => ['ar', '10:00 AM', '11:00 AM'],
]);

it('keeps customer-facing headings out of the salon notification', function () {
    $html = (new BookingNotificationMail($this->appointment, 'LookUp', '€', 'de'))->render();

    expect($html)
        ->toContain('Leistungen')
        ->not->toContain('Ihre Buchungsdetails')
        ->not->toContain('Ihre Leistungen')
        ->not->toContain('Zahlungsübersicht')
        ->not->toContain('Dauer')
        ->not->toContain('€')
        ->not->toContain('Zahlungsmethode')
        ->not->toContain('Barzahlung')
        ->toContain('<span dir="ltr">10:00</span>')
        ->not->toContain('11:00');
});
it('picks the customer email language from the request, falling back to German', function () {
    expect(BookingMailService::customerLocale('ar'))->toBe('ar')
        ->and(BookingMailService::customerLocale('en'))->toBe('en')
        ->and(BookingMailService::customerLocale('fr'))->toBe('de')
        ->and(BookingMailService::customerLocale(null))->toBe('de');
});

it('queues the confirmation in the booking request language, not users.locale', function () {
    Mail::fake();
    $this->salon->customer->update(['locale' => 'en']); // the never-filled default
    app()->setLocale('ar');

    app(BookingMailService::class)->sendForNewBooking($this->appointment);

    Mail::assertQueued(BookingConfirmationMail::class, fn (BookingConfirmationMail $mail) => $mail->locale === 'ar');
});

it('stores the request language on a newly scheduled reminder', function () {
    app()->setLocale('ar');
    $this->salon->customer->update(['locale' => 'en']);
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 06:00:00'));

    $reminder = app(AppointmentReminderService::class)->scheduleReminder(
        $this->appointment,
        Carbon::parse(SalonFixture::DATE . ' 08:00:00'),
    );

    expect($reminder->locale)->toBe('ar');
});

/**
 * Gives the fixture appointment a "Hair Cut" line with a German and an Arabic
 * name, so the reminder can be checked for the email-language service name.
 */
function withTranslatedHaircut(): void
{
    $salon = test()->salon;
    foreach (['de' => 'Deutsch', 'ar' => 'العربية'] as $code => $name) {
        Language::firstOrCreate(['code' => $code], ['name' => $name, 'native_name' => $name, 'is_active' => true, 'is_default' => false]);
    }
    $salon->service->translate('de', ['name' => 'Haarschnitt', 'language_code' => 'de']);
    $salon->service->translate('ar', ['name' => 'قص الشعر', 'language_code' => 'ar']);

    AppointmentService::create([
        'appointment_id' => test()->appointment->id,
        'service_id' => $salon->service->id,
        'service_name' => 'Hair Cut',
        'duration_minutes' => 60,
        'price' => 100,
        'sequence_order' => 1,
    ]);
}

function reminderIn(string $locale): string
{
    return (new AppointmentReminderMail(test()->appointment->fresh(), 'Look up', $locale))->render();
}

it('renders the German reminder with the salon-approved wording and five detail lines', function () {
    withTranslatedHaircut();
    test()->salon->setSetting('company_address', 'Landshut');
    $html = reminderIn('de');
    $name = test()->appointment->customer_name;

    expect($html)
        ->toContain('Look up – Erinnerung an Deinen Termin')
        ->toContain("Hallo {$name},")
        ->toContain('Wir möchten Dich daran erinnern, dass Du einen Termin bei Look up hast.')
        ->toContain('Hier sind die Details zu Deinem Termin:')
        ->toContain('Datum:')
        ->toContain('Mittwoch, 9. September 2026')
        ->toContain('Uhrzeit:')
        ->toContain('<span dir="ltr">10:00</span> Uhr')
        ->toContain('Friseur:')
        ->toContain('>Available<')                    // provider's first name only
        ->not->toContain('Available Provider')
        ->toContain('Service:')
        ->toContain('Haarschnitt')                    // service name in the email's language
        ->toContain('Salon:')
        ->toContain('Look up, Landshut')
        ->toContain('Wir freuen uns auf Deinen Besuch!')
        ->toContain('Dein Look up Team')
        ->toContain("Mehr als ein Haarschnitt.<br />\nDein Look. Unser Ziel.")
        ->toContain('data:image/png;base64')          // embedded, not linked to APP_URL
        // The salon asked for exactly these lines — nothing else from the booking.
        ->not->toContain(test()->appointment->number)
        ->not->toContain('Barzahlung')
        ->not->toContain('Bitte kurz')
        ->not->toContain('Dauer')
        ->not->toContain('€')
        ->not->toContain('automatisch versendete');
});

it('renders the Arabic reminder right-to-left with the salon-approved wording', function () {
    withTranslatedHaircut();
    $html = reminderIn('ar');

    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('Look up – تذكير بموعدك')
        ->toContain('نود تذكيرك بأن لديك موعدًا لدى Look up.')
        ->toContain('فيما يلي تفاصيل موعدك:')
        ->toContain('الأربعاء، 9 سبتمبر 2026')
        ->toContain('الساعة <span dir="ltr">10:00</span>')
        ->toContain('الحلاق:')
        ->toContain('قص الشعر')
        ->toContain('الصالون:')
        ->toContain('نتطلع إلى زيارتك!')
        ->toContain('فريق Look up')
        ->toContain("أكثر من مجرد قصة شعر.<br />\nمظهرك. هدفنا.")
        ->not->toContain(test()->appointment->number)
        ->not->toContain('نقداً');
});

it('shows the salon name alone when no address is set', function () {
    expect(reminderIn('de'))->toContain('>Look up<');
});

it('renders one card per block of a split booking', function () {
    $second = $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '15:00', '16:00');
    $second->update(['parent_appointment_id' => $this->appointment->id, 'customer_id' => $this->salon->customer->id]);

    $mail = new AppointmentReminderMail($this->appointment->fresh()->load('children'), 'Look up', 'de');
    $html = $mail->render();

    expect(substr_count($html, 'Uhrzeit:'))->toBe(2)
        ->and($html)->toContain('<span dir="ltr">10:00</span> Uhr')
        ->and($html)->toContain('<span dir="ltr">15:00</span> Uhr')
        ->and($html)->toContain('>Available<')
        ->and($html)->toContain('>HourlyLeave<');
});

it('links only the social platforms the landing page has a URL for', function () {
    LandingSection::create([
        'key' => LandingSection::INFO,
        'is_active' => true,
        'sort_order' => 1,
        'content' => ['social_links' => [
            ['platform' => 'instagram', 'url' => 'https://instagram.com/lookup.landshut'],
            ['platform' => 'facebook', 'url' => ''],
            ['platform' => 'whatsapp', 'url' => 'https://wa.me/49123'],
        ]],
    ]);

    expect(reminderIn('de'))
        ->toContain('href="https://instagram.com/lookup.landshut"')
        ->not->toContain('alt="Facebook"')
        ->not->toContain('wa.me');
});

it('sends the reminder email in the reminder language', function () {
    $this->seed(AppSettingSeeder::class);
    Mail::fake();
    Notification::fake();
    foreach (['reminder_push_enabled' => false, 'reminder_sms_enabled' => false, 'reminder_email_enabled' => true] as $key => $value) {
        UserSetting::updateOrCreate(['user_id' => $this->salon->customer->id, 'key' => $key], ['value' => $value]);
    }
    app()->instance(SmsService::class, Mockery::mock(SmsService::class));

    $reminder = AppointmentReminder::create([
        'appointment_id' => $this->appointment->id,
        'user_id' => $this->salon->customer->id,
        'remind_at' => now()->addMinutes(5),
        'status' => AppointmentReminder::STATUS_PENDING,
        'active_slot' => AppointmentReminder::ACTIVE_SLOT,
        'locale' => 'de',
        'title_key' => 'appointment_reminder.title',
        'message_key' => 'appointment_reminder.message',
        'params' => [],
    ]);

    app()->call([new SendAppointmentReminderJob($reminder->id), 'handle']);

    Mail::assertSent(AppointmentReminderMail::class, function (AppointmentReminderMail $mail) {
        $html = $mail->render();

        return $mail->locale === 'de'
            && str_contains($html, 'Erinnerung an Deinen Termin')
            && str_contains($html, 'Wir freuen uns auf Deinen Besuch!')
            && ! str_contains($html, test()->appointment->number);
    });
});
