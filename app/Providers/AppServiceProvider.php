<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Filament\Auth\StaffLoginResponse;
use App\Http\Middleware\EnsureStaffDashboardAccess;
use App\Support\ThrottleKey;
use App\Services\Landing\LandingContent;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsManager;
use App\Notifications\Filament\TranslatableNotification;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Notifications\Notification as BaseNotification;
use Livewire\Livewire;

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
        // The `api` limiter backs `$middleware->throttleApi()` in bootstrap/app.php.
        // Laravel 11 dropped RouteServiceProvider — where this used to live — so
        // calling throttleApi() without redefining it made EVERY /api/* request die
        // with MissingRateLimiterException ("Rate limiter [api] is not defined"),
        // i.e. a blanket HTTP 500 on the whole mobile API. Do not remove.
        //
        // Authenticated callers are keyed by user id so one device cannot spend
        // another's budget; anonymous callers fall back to IP. That IP is only
        // trustworthy because bootstrap/app.php pins trustProxies to loopback
        // (CFG-02) — if a real proxy is ever put in front, fix that first or this
        // key becomes attacker-chosen again.
        //
        // This is the outer ceiling only. The tighter per-route limits in
        // routes/api.php (throttle:5,1 on login, throttle:6,1 on OTP …) still apply
        // on top of it and are what actually protect the sensitive endpoints.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->registerAuthRateLimiters();
        $this->registerStaffDashboardPersistentMiddleware();
    }

    /**
     * Re-apply the Staff Dashboard gate to Livewire ACTION requests (AUTHZ-01).
     *
     * The dashboard's routes carry EnsureStaffDashboardAccess, but that only
     * guards the initial page load. Everything the page then DOES —
     * processPayment, deleteAppointment, the CustomerLookup search — travels as
     * `POST /livewire/update`, a completely different route whose middleware is
     * just the `web` group: no `auth`, no permission check, no is_active check.
     * The component snapshot is HMAC-signed so it cannot be forged from nothing,
     * but a snapshot the browser ALREADY holds keeps working, which is exactly
     * the case that matters here — the employee who was deactivated while their
     * tab was open.
     *
     * Livewire re-runs middleware across update requests only when it has been
     * declared persistent, so without this line the middleware fix would stop
     * the fired employee's next page load while leaving the money-touching AJAX
     * endpoint fully operational.
     *
     * Mechanics: on dehydrate Livewire memoises the ORIGINAL path/method; on the
     * next update it rebuilds that request, matches the route, and pipes it
     * through whichever of its middleware appear in the persistent list. A
     * RedirectResponse from the pipeline is turned into an abort, which the
     * Livewire JS follows — so the disabled-account redirect reaches the browser
     * as a real navigation to the login page.
     */
    private function registerStaffDashboardPersistentMiddleware(): void
    {
        Livewire::addPersistentMiddleware(EnsureStaffDashboardAccess::class);
    }

    /**
     * Per-endpoint limits for the authentication surface (AUTH-01).
     *
     * Before this, `login`, `register`, `refresh` and every OTP route carried no
     * throttle of their own. The `api` limiter above is a 60/min ceiling, which is
     * nowhere near tight enough for credential stuffing or for brute-forcing a
     * six-digit OTP — 60 guesses a minute still exhausts a 1,000,000-value space
     * inside the code's lifetime once run in parallel from a handful of addresses.
     *
     * Each limiter returns an ARRAY of limits; Laravel enforces all of them and
     * the first to trip wins. The two dimensions are:
     *
     *   ->by('...:' . $request->ip())            the source of the traffic
     *   ->by('...:' . ThrottleKey::forIdentifier($request))   the account targeted
     *
     * Keeping both matters because they fail in opposite directions: an attacker
     * rotating IPs slips every per-IP bucket but stays pinned to one account key,
     * and an attacker spraying many accounts from one host stays pinned to one IP
     * key. Neither dimension alone is sufficient.
     *
     * Every `by()` string is prefixed with the limiter name. Rate limiter buckets
     * are shared across the whole cache, so an unprefixed key would let a request
     * to one endpoint spend another endpoint's budget for the same address.
     */
    private function registerAuthRateLimiters(): void
    {
        $limits = config('rate_limits');

        // A 429 from these routes must look like every other API error: the mobile
        // app parses `success`/`message`, and Laravel's stock ThrottleRequests
        // response is a bare `{"message": "Too Many Attempts."}` in English only.
        $tooManyRequests = function (Request $request, array $headers) {
            $seconds = (int) ($headers['Retry-After'] ?? 60);

            return response()->json([
                'success' => false,
                'message' => __('auth.throttle_generic', ['seconds' => $seconds]),
                'error_type' => 'rate_limited',
                'retry_after' => $seconds,
            ], 429, $headers);
        };

        // Resolves the account dimension, falling back to the IP when the request
        // carries no readable identifier. The fallback is what stops a malformed
        // body (`email[]=x`, or no email at all) from becoming an unlimited path.
        $accountKey = function (Request $request, string $scope): string {
            $identifier = ThrottleKey::forIdentifier($request);

            return $identifier !== null
                ? "{$scope}:acct:{$identifier}"
                : "{$scope}:ip:{$request->ip()}";
        };

        RateLimiter::for('auth-login', function (Request $request) use ($limits, $tooManyRequests, $accountKey) {
            return [
                Limit::perMinute($limits['login']['per_ip'])
                    ->by('auth-login:ip:' . $request->ip())
                    ->response($tooManyRequests),

                Limit::perMinute($limits['login']['per_account'])
                    ->by($accountKey($request, 'auth-login'))
                    ->response($tooManyRequests),

                // Long-window backstop. Without it the per-minute limit still
                // permits 7,200 guesses a day against a single account.
                Limit::perHour($limits['login']['per_account_hour'])
                    ->by($accountKey($request, 'auth-login-hourly'))
                    ->response($tooManyRequests),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request) use ($limits, $tooManyRequests) {
            return Limit::perHour($limits['register']['per_ip_hour'])
                ->by('auth-register:ip:' . $request->ip())
                ->response($tooManyRequests);
        });

        RateLimiter::for('auth-refresh', function (Request $request) use ($limits, $tooManyRequests) {
            return Limit::perMinute($limits['refresh']['per_ip'])
                ->by('auth-refresh:ip:' . $request->ip())
                ->response($tooManyRequests);
        });

        RateLimiter::for('otp-send', function (Request $request) use ($limits, $tooManyRequests, $accountKey) {
            return [
                Limit::perMinute($limits['otp_send']['per_ip'])
                    ->by('otp-send:ip:' . $request->ip())
                    ->response($tooManyRequests),

                // Caps how much SMS credit one destination can burn in an hour, and
                // how badly one victim's phone can be flooded, regardless of source.
                Limit::perHour($limits['otp_send']['per_destination_hour'])
                    ->by($accountKey($request, 'otp-send'))
                    ->response($tooManyRequests),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) use ($limits, $tooManyRequests, $accountKey) {
            return [
                Limit::perMinute($limits['otp_verify']['per_ip'])
                    ->by('otp-verify:ip:' . $request->ip())
                    ->response($tooManyRequests),

                Limit::perMinute($limits['otp_verify']['per_destination'])
                    ->by($accountKey($request, 'otp-verify'))
                    ->response($tooManyRequests),

                Limit::perHour($limits['otp_verify']['per_destination_hour'])
                    ->by($accountKey($request, 'otp-verify-hourly'))
                    ->response($tooManyRequests),
            ];
        });

        RateLimiter::for('auth-social', function (Request $request) use ($limits, $tooManyRequests) {
            return Limit::perMinute($limits['social']['per_ip'])
                ->by('auth-social:ip:' . $request->ip())
                ->response($tooManyRequests);
        });
    }
}
