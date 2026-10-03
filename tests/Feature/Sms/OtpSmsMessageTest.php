<?php

namespace Tests\Feature\Sms;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Jobs\SendOtpDeliveryJob;
use App\Models\User;
use App\Services\OtpDeliveryService;
use App\Services\OtpService;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OtpSmsMessageTest extends TestCase
{
    use RefreshDatabase;

    private function texts(): OtpDeliveryService
    {
        return app(OtpDeliveryService::class);
    }

    public function test_german_verification_text(): void
    {
        $this->assertSame(
            'Ihr Bestätigungscode lautet: 123456. Bitte geben Sie diesen Code in der App ein, um Ihre Telefonnummer zu bestätigen. Der Code ist 10 Minuten gültig.',
            $this->texts()->smsText('123456', 10, OtpPurpose::ACCOUNT_VERIFICATION, 'de'),
        );
    }

    public function test_arabic_verification_text(): void
    {
        $this->assertSame(
            'رمز التحقق الخاص بك هو: 123456. يرجى إدخال هذا الرمز في التطبيق لتأكيد رقم هاتفك. الرمز صالح لمدة 10 دقائق.',
            $this->texts()->smsText('123456', 10, OtpPurpose::ACCOUNT_VERIFICATION, 'ar'),
        );
    }

    public function test_english_verification_text(): void
    {
        $this->assertSame(
            'Your verification code is: 123456. Please enter this code in the app to confirm your phone number. The code is valid for 10 minutes.',
            $this->texts()->smsText('123456', 10, OtpPurpose::ACCOUNT_VERIFICATION, 'en'),
        );
    }

    public function test_password_reset_text_does_not_talk_about_the_phone_number(): void
    {
        foreach (['de' => 'Passwort zurückzusetzen', 'ar' => 'لإعادة تعيين كلمة المرور', 'en' => 'reset your password'] as $locale => $phrase) {
            $text = $this->texts()->smsText('123456', 10, OtpPurpose::PASSWORD_RESET, $locale);

            $this->assertStringContainsString($phrase, $text, $locale);
            $this->assertStringContainsString('123456', $text, $locale);
        }
    }

    public function test_unknown_or_missing_locale_falls_back_to_german(): void
    {
        $german = $this->texts()->smsText('123456', 10, OtpPurpose::ACCOUNT_VERIFICATION, 'de');

        $this->assertSame($german, $this->texts()->smsText('123456', 10, OtpPurpose::ACCOUNT_VERIFICATION, 'fr'));
        $this->assertSame($german, $this->texts()->smsText('123456', 10, OtpPurpose::ACCOUNT_VERIFICATION, null));
    }

    public function test_minutes_follow_the_real_lifetime_and_grammar(): void
    {
        $cases = [
            ['de', 1, '1 Minute gültig'],
            ['de', 15, '15 Minuten gültig'],
            ['en', 1, 'valid for 1 minute.'],
            ['ar', 1, 'لمدة دقيقة واحدة.'],
            ['ar', 2, 'لمدة دقيقتين.'],
            ['ar', 5, 'لمدة 5 دقائق.'],
            ['ar', 15, 'لمدة 15 دقيقة.'],
        ];

        foreach ($cases as [$locale, $minutes, $expected]) {
            $this->assertStringContainsString(
                $expected,
                $this->texts()->smsText('123456', $minutes, OtpPurpose::ACCOUNT_VERIFICATION, $locale),
                "{$locale}/{$minutes}",
            );
        }
    }

    public function test_generate_captures_request_locale_and_purpose_on_the_job(): void
    {
        Queue::fake();
        app()->setLocale('ar');

        $user = User::factory()->create(['phone' => '+4915223917565']);

        app(OtpService::class)->generate($user, 6, OtpType::SMS_OTP, OtpPurpose::PASSWORD_RESET);

        Queue::assertPushed(SendOtpDeliveryJob::class, fn (SendOtpDeliveryJob $job) => $job->locale === 'ar'
            && $job->purpose === OtpPurpose::PASSWORD_RESET);
    }

    public function test_sms_reaches_the_gateway_in_the_captured_language(): void
    {
        config([
            'sms.enabled' => true,
            'sms.driver' => 'seven',
            'sms.default_country_code' => '49',
            'sms.drivers.seven.api_key' => 'test-key',
            'sms.drivers.seven.from' => 'LookUp',
            'sms.drivers.seven.base_url' => 'https://gateway.seven.io/api',
            'otp.ttl_minutes' => 10,
        ]);
        $this->app->instance(SmsGateway::class, new SmsManager($this->app));
        Http::fake(['gateway.seven.io/*' => Http::response(['success' => '100', 'messages' => [['id' => '1', 'success' => true]]])]);

        $user = User::factory()->create(['phone' => '+4915223917565']);

        (new SendOtpDeliveryJob($user->id, '654321', OtpType::SMS_OTP, now()->addMinutes(10)->toIso8601String(), OtpPurpose::ACCOUNT_VERIFICATION, 'de'))
            ->handle(app(OtpDeliveryService::class));

        Http::assertSent(fn (Request $request) => $request['from'] === 'LookUp'
            && $request['text'] === 'Ihr Bestätigungscode lautet: 654321. Bitte geben Sie diesen Code in der App ein, um Ihre Telefonnummer zu bestätigen. Der Code ist 10 Minuten gültig.');
    }

    public function test_job_queued_before_the_upgrade_still_delivers_in_german(): void
    {
        $user = User::factory()->create(['phone' => '+4915223917565']);
        $job = new SendOtpDeliveryJob($user->id, '111222', OtpType::SMS_OTP, now()->addMinutes(10)->toIso8601String());

        // Mimic an old payload: these properties did not exist when it was serialized.
        unset($job->purpose, $job->locale);

        $sms = new class implements SmsGateway {
            public array $sent = [];

            public function send(string $to, string $text, array $options = []): \App\Services\Sms\SmsResult
            {
                $this->sent[] = $text;

                return \App\Services\Sms\SmsResult::skipped('fake', $to, null);
            }

            public function name(): string
            {
                return 'fake';
            }
        };

        $job->handle(new OtpDeliveryService($sms));

        $this->assertCount(1, $sms->sent);
        $this->assertStringStartsWith('Ihr Bestätigungscode lautet: 111222.', $sms->sent[0]);
    }
}
