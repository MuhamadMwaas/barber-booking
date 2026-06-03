<?php

namespace App\Providers;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use App\Filament\Auth\StaffLoginResponse;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
