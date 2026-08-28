<?php

namespace App\Console\Commands;

use App\Services\Sms\Drivers\SevenSmsDriver;
use App\Services\Sms\Exceptions\SmsDeliveryException;
use App\Services\Sms\SmsManager;
use Illuminate\Console\Command;

/**
 * Checks the seven.io account credit.
 *
 * The point is not the number — it is the cheapest end-to-end proof that the
 * configured API key is valid, has endpoint rights, and is reachable from this
 * server, without sending an SMS or spending a cent.
 */
class SmsBalanceCommand extends Command
{
    protected $signature = 'sms:balance';

    protected $description = 'Show the seven.io account balance and verify the configured API key';

    public function handle(SmsManager $manager): int
    {
        $driver = $manager->driver('seven');

        if (! $driver instanceof SevenSmsDriver) {
            $this->error('The seven.io driver could not be resolved.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line('  <fg=gray>SMS channel</> ' . (config('sms.enabled') ? '<fg=green>enabled</>' : '<fg=yellow>disabled (SMS_ENABLED=false)</>'));
        $this->line('  <fg=gray>Active driver</> ' . config('sms.driver'));
        $this->line('  <fg=gray>Sender ID</> ' . (config('sms.drivers.seven.from') ?: '<fg=gray>(account default)</>'));
        $this->line('  <fg=gray>Endpoint</> ' . config('sms.drivers.seven.base_url'));
        $this->line('');

        try {
            $balance = $driver->balance();
        } catch (SmsDeliveryException $exception) {
            $this->error('  ' . $exception->getMessage());

            return self::FAILURE;
        }

        if ($balance === null) {
            $this->error('  SEVEN_API_KEY is not set — nothing to check.');
            $this->line('  <fg=gray>Create a key at https://dashboard.seven.io/developer/api</>');

            return self::FAILURE;
        }

        $this->info(sprintf('  Balance: %.3f %s', $balance['amount'], $balance['currency']));

        if ($balance['amount'] <= 0) {
            $this->warn('  The account has no credit left — sends will fail with code 500.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
