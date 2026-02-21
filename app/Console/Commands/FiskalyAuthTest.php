<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\FiskalyClient;

class FiskalyAuthTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiskaly:auth-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Fiskaly authenticate() and print the access token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Fiskaly authenticate()...');

        try {
            $client = app(FiskalyClient::class);
            $token = $client->authenticate();

            $this->info('Access token:');
            $this->line($token);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Authentication failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
