<?php

namespace App\Services\Sms;

use App\Services\Sms\Drivers\LogSmsDriver;
use App\Services\Sms\Drivers\SevenSmsDriver;
use App\Services\Sms\Drivers\VonageSmsDriver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves and delegates to the gateway named by `config('sms.driver')`.
 *
 * It is itself an SmsGateway, so application code injects this and never has to
 * know which provider is live. It also owns the one behaviour that must hold for
 * every provider: `config('sms.enabled') === false` means nothing leaves the app.
 */
class SmsManager implements SmsGateway
{
    /** @var array<string, class-string<SmsGateway>> */
    private const DRIVERS = [
        'seven' => SevenSmsDriver::class,
        'vonage' => VonageSmsDriver::class,
        'log' => LogSmsDriver::class,
    ];

    /** @var array<string, SmsGateway> */
    private array $resolved = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return $this->defaultDriverName();
    }

    /**
     * @param  array{from?: string|null, ttl?: int|null, label?: string|null, flash?: bool, foreign_id?: string|null}  $options
     *
     * @throws Exceptions\SmsDeliveryException
     */
    public function send(string $to, string $text, array $options = []): SmsResult
    {
        $driverName = $this->defaultDriverName();

        // The master switch is checked here rather than in each driver so that
        // "SMS is off" means the same thing no matter which provider is selected
        // — including the OTP endpoints, which read the same flag to decide
        // whether it is still safe to echo the code back in the response.
        if (! config('sms.enabled', false)) {
            Log::info('SMS delivery skipped: the SMS channel is disabled (SMS_ENABLED=false).', [
                'driver' => $driverName,
                'to' => $to,
            ]);

            return SmsResult::skipped($driverName, $to, $options['from'] ?? null);
        }

        return $this->driver($driverName)->send($to, $text, $options);
    }

    /**
     * Resolve a specific gateway, bypassing the configured default.
     *
     * Used by tooling that must talk to one provider regardless of config —
     * `php artisan sms:balance`, for instance.
     */
    public function driver(?string $name = null): SmsGateway
    {
        $name = $name ?: $this->defaultDriverName();

        if (! isset(self::DRIVERS[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported SMS driver [%s]. Supported drivers: %s.',
                $name,
                implode(', ', array_keys(self::DRIVERS)),
            ));
        }

        return $this->resolved[$name] ??= $this->container->make(self::DRIVERS[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function availableDrivers(): array
    {
        return array_keys(self::DRIVERS);
    }

    private function defaultDriverName(): string
    {
        return (string) config('sms.driver', 'seven');
    }
}
