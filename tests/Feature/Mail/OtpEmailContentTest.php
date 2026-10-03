<?php

/**
 * Wording and language of the one-time-code email (SendOtpMail).
 */

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Jobs\SendOtpDeliveryJob;
use App\Mail\SendOtpMail;
use App\Models\User;
use App\Services\OtpDeliveryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['app.name' => 'LookUp']);
    $this->expiresAt = Carbon::parse('2026-09-20 12:50:00', config('app.timezone'));
});

function otpMail(string $locale, OtpPurpose $purpose = OtpPurpose::ACCOUNT_VERIFICATION): SendOtpMail
{
    return new SendOtpMail('844607', 'Fareed Naseraldeen', test()->expiresAt, $purpose, $locale, 'LookUp');
}

it('renders the German verification email with the requested wording', function () {
    $mail = otpMail('de');
    $html = $mail->render();

    expect($mail->locale)->toBe('de');
    $mail->assertHasSubject('LookUp – E-Mail-Adresse bestätigen');
    expect($html)
        ->toContain('Hallo Fareed Naseraldeen,')
        ->toContain('Bitte gib den folgenden Bestätigungscode ein, um Deine E-Mail-Adresse zu bestätigen und mit der Registrierung fortzufahren.')
        ->toContain('Der Code ist bis 20.09.2026 um 12:50 Uhr gültig.')
        ->toContain('844607')
        ->toContain('Wenn Du diesen Code nicht angefordert hast, kannst Du diese E-Mail einfach ignorieren.')
        ->toContain('Dein LookUp Team');
});

it('renders the Arabic verification email right-to-left with the requested wording', function () {
    $mail = otpMail('ar');
    $html = $mail->render();

    $mail->assertHasSubject('LookUp – تأكيد عنوان البريد الإلكتروني');
    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('مرحبًا Fareed Naseraldeen،')
        ->toContain('أدخل رمز التحقق التالي لتأكيد عنوان بريدك الإلكتروني ومتابعة إنشاء حسابك.')
        ->toContain('الرمز صالح حتى 20.09.2026 الساعة 12:50.')
        ->toContain('إذا لم تطلب هذا الرمز، يمكنك تجاهل هذا البريد الإلكتروني.')
        ->toContain('فريق LookUp');
});

it('gives the password reset its own subject and intro', function () {
    $mail = otpMail('de', OtpPurpose::PASSWORD_RESET);
    $html = $mail->render();

    $mail->assertHasSubject('LookUp – Passwort zurücksetzen');
    expect($html)
        ->toContain('um Dein Passwort zurückzusetzen')
        ->not->toContain('Registrierung');
});

it('falls back to German for an unknown or missing locale', function () {
    expect(otpMail('fr')->locale)->toBe('de')
        ->and((new SendOtpMail('1', 'X', test()->expiresAt))->locale)->toBe('de')
        ->and(otpMail('en')->locale)->toBe('en');
});

it('delivers the email in the language and purpose carried by the job', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'fareed@example.com']);

    (new SendOtpDeliveryJob($user->id, '844607', OtpType::EMAIL_OTP, test()->expiresAt->toIso8601String(), OtpPurpose::PASSWORD_RESET, 'ar'))
        ->handle(app(OtpDeliveryService::class));

    Mail::assertSent(SendOtpMail::class, fn (SendOtpMail $mail) => $mail->hasTo('fareed@example.com')
        && $mail->locale === 'ar'
        && $mail->purpose === OtpPurpose::PASSWORD_RESET);
});
