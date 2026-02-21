<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Fiskaly\FiskalyClient;
use App\Services\Fiskaly\TssService;
use App\Services\Fiskaly\ClientService;
use App\Services\Fiskaly\TransactionService;
use App\Services\Fiskaly\ReceiptService;
use App\Services\Fiskaly\FiskalyService;

class FiskalyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register FiskalyClient as singleton
        $this->app->singleton(FiskalyClient::class, function ($app) {
            return new FiskalyClient();
        });

        // Register TssService
        $this->app->singleton(TssService::class, function ($app) {
            return new TssService($app->make(FiskalyClient::class));
        });

        // Register ClientService
        $this->app->singleton(ClientService::class, function ($app) {
            return new ClientService($app->make(FiskalyClient::class));
        });

        // Register TransactionService
        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService($app->make(FiskalyClient::class));
        });

        // Register ReceiptService
        $this->app->singleton(ReceiptService::class, function ($app) {
            return new ReceiptService($app->make(TransactionService::class));
        });

        // Register main FiskalyService
        $this->app->singleton(FiskalyService::class, function ($app) {
            return new FiskalyService(
                $app->make(FiskalyClient::class),
                $app->make(TssService::class),
                $app->make(ClientService::class),
                $app->make(TransactionService::class),
                $app->make(ReceiptService::class)
            );
        });

        // Register alias for easier access
        $this->app->alias(FiskalyService::class, 'fiskaly');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/fiskaly.php' => config_path('fiskaly.php'),
        ], 'fiskaly-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/create_fiskaly_tss_table.php' =>
                database_path('migrations/'.date('Y_m_d_His', time()).'_create_fiskaly_tss_table.php'),
        ], 'fiskaly-migrations');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            FiskalyClient::class,
            TssService::class,
            ClientService::class,
            TransactionService::class,
            ReceiptService::class,
            FiskalyService::class,
            'fiskaly',
        ];
    }
}
