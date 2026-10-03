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
        ->toContain('<span dir="ltr">10:00 - 11:00</span>') // 24h clock
        ->toContain('Mitarbeiter/in')
        ->toContain('Zahlungsmethode')
        ->toContain('Barzahlung')
        ->toContain('Hinweise')
        ->toContain('Ihre Leistungen')
        ->toContain('Leistung')
        ->toContain('Preis')
        ->toContain('Zahlungsübersicht')
        ->toContain('Zwischensumme')
        ->toContain('Gesamtpreis')
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
        ->toContain('نقداً')
        ->toContain('الملاحظات')
        ->toContain('الخدمات التي اخترتها')
        ->toContain('ملخص الدفع')
        ->toContain('السعر الإجمالي')
        ->toContain('شكراً لاختيارك LookUp! نتطلع إلى زيارتك.')
        ->toContain('هذه رسالة بريد إلكتروني مرسلة تلقائياً. يرجى عدم الرد مباشرةً على هذه الرسالة.');
});

it('keeps customer-facing headings out of the salon notification', function () {
    $html = (new BookingNotificationMail($this->appointment, 'LookUp', '€', 'de'))->render();

    expect($html)
        ->toContain('Leistungen')
        ->not->toContain('Ihre Buchungsdetails')
        ->not->toContain('Ihre Leistungen')
        ->not->toContain('Zahlungsübersicht');
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

it('sends the reminder email in the confirmation layout and the reminder language', function () {
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
            && str_contains($html, 'Terminerinnerung — ' . test()->appointment->number)
            && str_contains($html, 'Wir möchten Sie an Ihren bevorstehenden Termin bei LookUp erinnern.')
            && str_contains($html, 'Ihre Buchungsdetails')
            && str_contains($html, 'Zahlungsübersicht')
            && str_contains($html, 'Wir freuen uns auf Ihren Besuch bei LookUp!')
            && str_contains($html, 'Dies ist eine automatisch versendete E-Mail.');
    });
});
