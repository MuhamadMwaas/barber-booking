<?php

namespace App\Console\Commands;

use App\Services\Sms\Exceptions\SmsDeliveryException;
use App\Services\Sms\SmsManager;
use Illuminate\Console\Command;

/**
 * Sends one SMS through the configured gateway, right now, synchronously.
 *
 * Deliberately bypasses SendOtpDeliveryJob and the queue: when a test message
 * does not arrive, the first question is always "did the gateway reject it, or
 * is no worker running?". This command answers only the first, which is what
 * makes it useful for diagnosing the gateway itself.
 */
class SmsTestCommand extends Command
{
    protected $signature = 'sms:test
        {phone : Recipient number, e.g. +971501010101}
        {text? : Message body (a default test text is used when omitted)}
        {--real : Send for real even when SEVEN_DEBUG=true (spends credit)}';

    protected $description = 'Send a single test SMS through the active gateway';

    public function handle(SmsManager $manager): int
    {
        $phone = (string) $this->argument('phone');
        $text = (string) ($this->argument('text')
            ?: 'Test message from ' . config('app.name') . ' at ' . now()->format('H:i:s') . '.');

        $dryRun = filter_var(config('sms.drivers.seven.debug'), FILTER_VALIDATE_BOOLEAN)
            && ! $this->option('real');

        if ($this->option('real')) {
            config(['sms.drivers.seven.debug' => false]);
        }

        $this->line('');
        $this->line('  <fg=gray>Driver</>    ' . config('sms.driver'));
        $this->line('  <fg=gray>Channel</>   ' . (config('sms.enabled') ? '<fg=green>enabled</>' : '<fg=red>DISABLED (SMS_ENABLED=false) — nothing will be sent</>'));
        $this->line('  <fg=gray>Sender</>    ' . (config('sms.drivers.seven.from') ?: '(account default)'));
        $this->line('  <fg=gray>To</>        ' . $phone);
        $this->line('  <fg=gray>Mode</>      ' . ($dryRun
            ? '<fg=yellow>DRY RUN (SEVEN_DEBUG=true) — validated and priced, but NOT delivered</>'
            : '<fg=green>REAL SEND — this spends account credit</>'));
        $this->line('');

        try {
            $result = $manager->send($phone, $text);
        } catch (SmsDeliveryException $exception) {
            $this->error('  ✗ ' . $exception->getMessage());

            if ($exception->statusCode !== null) {
                $this->line('  <fg=gray>Gateway status code: ' . $exception->statusCode . '</>');
            }

            $this->line('');

            return self::FAILURE;
        }

        if ($result->skipped) {
            $this->warn('  ⊘ Skipped — the gateway is disabled or not fully configured. Nothing was sent.');
            $this->line('  <fg=gray>Check SMS_ENABLED and SEVEN_API_KEY, then run: php artisan config:clear</>');
            $this->line('');

            return self::FAILURE;
        }

        $this->info($dryRun
            ? '  ✓ Accepted by the gateway (dry run — no SMS delivered, no credit spent).'
            : '  ✓ Sent.');

        $this->line('  <fg=gray>Normalised recipient</> ' . $result->to);

        if ($result->messageIds !== []) {
            $this->line('  <fg=gray>Message ID</>           ' . implode(', ', $result->messageIds));
        }

        if ($result->price !== null) {
            $this->line('  <fg=gray>Price</>                ' . sprintf('%.4f', $result->price));
        }

        if ($result->balance !== null) {
            $this->line('  <fg=gray>Remaining balance</>    ' . sprintf('%.3f', $result->balance));
        }

        $this->line('');

        return self::SUCCESS;
    }
}
