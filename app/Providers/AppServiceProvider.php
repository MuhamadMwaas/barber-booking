<?php

namespace App\Providers;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use App\Filament\Auth\StaffLoginResponse;
use App\Services\Landing\LandingContent;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsManager;
use App\Notifications\Filament\TranslatableNotification;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Notifications\Notification as BaseNotification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BaseNotification::class, TranslatableNotification::class);

        // Providers land on the StaffDashboard after login; everyone else keeps
        // the default Filament panel-home redirect.
        $this->app->bind(LoginResponse::class, StaffLoginResponse::class);

        $this->app->resolving(Command::class, function (Command $command, $app): void {
            $command->setLaravel($app);
        });

        // LandingContent memoises the landing page's section rows, so it must
        // never outlive a request. It is registered as a plain (non-shared)
        // binding on purpose: `singleton` and `scoped` both keep serving the
        // payload from before the last admin save — `scoped` instances are only
        // released by a persistent runtime's per-request flush, which neither
        // PHP-FPM nor the test runner performs. Sharing within a render is still
        // achieved: the controller resolves it once and passes it to the views.
        $this->app->bind(LandingContent::class, fn (): LandingContent => new LandingContent());

        // Every SMS in the app resolves the gateway through this one binding, so
        // switching provider is `SMS_DRIVER=` in .env and nothing else. Singleton
        // is safe here: the manager holds no per-request state and reads the
        // driver name from config on each send.
        $this->app->singleton(SmsManager::class);
        $this->app->alias(SmsManager::class, SmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
