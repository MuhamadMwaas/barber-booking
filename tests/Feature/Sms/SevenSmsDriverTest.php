<?php

namespace Tests\Feature\Sms;

use App\Services\Sms\Exceptions\SmsDeliveryException;
use App\Services\Sms\PhoneNumberNormalizer;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SevenSmsDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sms.enabled' => true,
            'sms.driver' => 'seven',
            'sms.default_country_code' => null,
            'sms.drivers.seven.api_key' => 'test-key',
            'sms.drivers.seven.from' => 'Barber',
            'sms.drivers.seven.base_url' => 'https://gateway.seven.io/api',
            'sms.drivers.seven.ttl' => null,
            'sms.drivers.seven.label' => null,
            'sms.drivers.seven.debug' => false,
        ]);
    }

    private function gateway(): SmsGateway
    {
        // Resolve fresh: the manager is a singleton and caches its drivers, so a
        // container instance left over from another test would ignore the config
        // set above.
        return new SmsManager($this->app);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function acceptedBody(array $overrides = []): array
    {
        return array_merge([
            'success' => '100',
            'total_price' => 0.075,
            'balance' => 593.994,
            'debug' => 'false',
            'sms_type' => 'direct',
            'messages' => [[
                'id' => '77229318510',
                'sender' => 'Barber',
                'recipient' => '971501010101',
                'text' => 'Your OTP code is 123456.',
                'encoding' => 'gsm',
                'parts' => 1,
                'price' => 0.075,
                'success' => true,
                'error' => null,
                'error_text' => null,
            ]],
        ], $overrides);
    }

    public function test_it_sends_an_sms_and_reports_price_and_balance(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody())]);

        $result = $this->gateway()->send('+971-50-101-0101', 'Your OTP code is 123456.');

        $this->assertTrue($result->sent);
        $this->assertFalse($result->skipped);
        $this->assertSame('seven', $result->driver);
        $this->assertSame(['77229318510'], $result->messageIds);
        $this->assertSame(0.075, $result->price);
        $this->assertSame(593.994, $result->balance);
    }

    public function test_it_authenticates_with_the_api_key_header_and_normalises_the_recipient(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody())]);

        $this->gateway()->send('+971-50-101-0101', '  Hello  ');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('https://gateway.seven.io/api/sms', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame('test-key', $request->header('X-Api-Key')[0]);

            // Separators would be rejected by the gateway with code 202.
            $this->assertSame('971501010101', $request['to']);
            $this->assertSame('Hello', $request['text']);
            $this->assertSame('Barber', $request['from']);

            return true;
        });
    }

    public function test_it_omits_optional_parameters_that_have_no_value(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody())]);

        $this->gateway()->send('+971501010101', 'Hello');

        // seven.io answers code 308 for parameters it does not recognise, and an
        // empty `from` reads as an invalid sender rather than "use the default".
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            $this->assertArrayNotHasKey('ttl', $data);
            $this->assertArrayNotHasKey('label', $data);
            $this->assertArrayNotHasKey('flash', $data);
            $this->assertArrayNotHasKey('foreign_id', $data);
            $this->assertArrayNotHasKey('debug', $data);

            return true;
        });
    }

    public function test_it_forwards_ttl_and_label_options(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody())]);

        $this->gateway()->send('+971501010101', 'Hello', ['ttl' => 10, 'label' => 'otp']);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('10', (string) $request['ttl']);
            $this->assertSame('otp', $request['label']);

            return true;
        });
    }

    public function test_it_sends_the_debug_flag_when_dry_run_is_enabled(): void
    {
        config(['sms.drivers.seven.debug' => true]);
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody(['debug' => 'true', 'total_price' => 0]))]);

        $this->gateway()->send('+971501010101', 'Hello');

        Http::assertSent(fn (Request $request): bool => (string) $request['debug'] === '1');
    }

    public function test_it_drops_a_sender_id_longer_than_the_gateway_limit(): void
    {
        // "BarberBooking" is 13 characters; seven.io caps alphanumeric senders at
        // 11 and rejects longer ones with code 201.
        config(['sms.drivers.seven.from' => 'BarberBooking']);
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody())]);

        $this->gateway()->send('+971501010101', 'Hello');

        Http::assertSent(fn (Request $request): bool => ! array_key_exists('from', $request->data()));
    }

    public function test_it_fails_when_the_api_key_is_rejected(): void
    {
        // seven.io answers HTTP 200 even for rejected messages: the body is the
        // only source of truth.
        Http::fake(['gateway.seven.io/*' => Http::response(['success' => '900'], 200)]);

        $this->expectException(SmsDeliveryException::class);
        $this->expectExceptionMessage('code 900');

        $this->gateway()->send('+971501010101', 'Hello');
    }

    public function test_it_fails_on_an_invalid_recipient(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response(['success' => '202'], 200)]);

        try {
            $this->gateway()->send('+971501010101', 'Hello');
            $this->fail('Expected an SmsDeliveryException.');
        } catch (SmsDeliveryException $exception) {
            $this->assertSame('202', $exception->statusCode);
            $this->assertStringContainsString('Invalid recipient', $exception->getMessage());
        }
    }

    public function test_it_fails_when_the_account_has_no_credit(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response(['success' => '500'], 200)]);

        try {
            $this->gateway()->send('+971501010101', 'Hello');
            $this->fail('Expected an SmsDeliveryException.');
        } catch (SmsDeliveryException $exception) {
            $this->assertSame('500', $exception->statusCode);
            $this->assertStringContainsString('Insufficient account credit', $exception->getMessage());
        }
    }

    public function test_it_fails_when_the_envelope_succeeds_but_a_message_was_rejected(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response($this->acceptedBody([
            'messages' => [[
                'id' => null,
                'recipient' => '971501010101',
                'success' => false,
                'error' => '202',
                'error_text' => 'invalid recipient',
            ]],
        ]))]);

        $this->expectException(SmsDeliveryException::class);
        $this->expectExceptionMessage('971501010101: invalid recipient');

        $this->gateway()->send('+971501010101', 'Hello');
    }

    public function test_it_understands_a_plain_text_status_code_response(): void
    {
        Http::fake(['gateway.seven.io/*' => Http::response('100', 200, ['Content-Type' => 'text/plain'])]);

        $result = $this->gateway()->send('+971501010101', 'Hello');

        $this->assertTrue($result->sent);
        $this->assertSame('100', $result->statusCode);
    }

    public function test_it_skips_without_calling_the_gateway_when_sms_is_disabled(): void
    {
        config(['sms.enabled' => false]);
        Http::fake();

        $result = $this->gateway()->send('+971501010101', 'Hello');

        $this->assertTrue($result->skipped);
        $this->assertFalse($result->sent);
        Http::assertNothingSent();
    }

    public function test_it_skips_without_calling_the_gateway_when_the_api_key_is_missing(): void
    {
        config(['sms.drivers.seven.api_key' => null]);
        Http::fake();

        $result = $this->gateway()->send('+971501010101', 'Hello');

        $this->assertTrue($result->skipped);
        Http::assertNothingSent();
    }

    public function test_it_reads_the_account_balance(): void
    {
        Http::fake(['gateway.seven.io/api/balance' => Http::response(['amount' => 12.5, 'currency' => 'EUR'])]);

        /** @var \App\Services\Sms\Drivers\SevenSmsDriver $driver */
        $driver = (new SmsManager($this->app))->driver('seven');

        $this->assertSame(['amount' => 12.5, 'currency' => 'EUR'], $driver->balance());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gateway.seven.io/api/balance'
            && $request->method() === 'GET');
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string}>
     */
    public static function numberProvider(): array
    {
        return [
            'separators are stripped' => ['+971-50-101-0101', null, '971501010101'],
            'spaces and parentheses are stripped' => ['+49 (0)171 999 9999', null, '4901719999999'],
            'double-zero prefix becomes a country code' => ['00491719999999', null, '491719999999'],
            'already bare international' => ['971501010101', null, '971501010101'],
            'national format with a default country code' => ['050-101-0101', '971', '971501010101'],
            'national format without a default country code' => ['0501010101', null, '0501010101'],
        ];
    }

    /**
     * @dataProvider numberProvider
     */
    public function test_it_normalises_numbers_for_the_gateway(string $input, ?string $countryCode, string $expected): void
    {
        $normalizer = new PhoneNumberNormalizer();

        $this->assertSame($expected, $normalizer->toGatewayFormat($input, $countryCode));
    }
}
