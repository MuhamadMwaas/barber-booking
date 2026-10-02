<?php

use App\Http\Middleware\EnforceJsonAcceptHeader;
use App\Http\Middleware\EnsureEmailIsVerifiedViaOtp;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocaleFromSession;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->appendToGroup('web', SetLocaleFromSession::class);

        // There is no route named `login` (staff sign in at the dashboard's own
        // /login, the panel at its own), so a guest hitting any `auth` web route
        // — /invoice/{id}/print, the template preview — died with a 500
        // RouteNotFoundException instead of being sent to sign in.
        $middleware->redirectGuestsTo(fn () => route('staff.dashboard.login'));

        $middleware->appendToGroup('api', [
            EnforceJsonAcceptHeader::class,
            SetApiLocale::class,
        ]);

        $middleware->throttleApi();


        // CFG-02 — X-Forwarded-* is client-supplied input, not a fact.
        // nginx terminates TLS on this same host and reaches PHP-FPM over a unix
        // socket, so the only legitimate "proxy" is loopback. `at: '*'` told Laravel
        // to believe any client's X-Forwarded-For, which made request()->ip() — the
        // key behind every `throttle:` limit in routes/api.php — attacker-chosen.
        //
        // X-Forwarded-Host is deliberately left untrusted: nothing on this host sets
        // it, and trusting it allows host-header injection into generated links
        // (e.g. password-reset URLs pointing at an attacker's domain).
        //
        // If a real proxy is added later (Cloudflare, ALB), replace the loopback
        // entries with that proxy's published ranges — never go back to '*'.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'verified.otp' => EnsureEmailIsVerifiedViaOtp::class,
            'verified.customer' => EnsureEmailIsVerifiedViaOtp::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A 422 on the phone field carries a machine-readable `error_code`
        // (INVALID_PHONE_NUMBER / PHONE_ALREADY_EXISTS) next to Laravel's usual
        // {message, errors} body, so the mobile app can react to the reason
        // instead of parsing translated text. Every other validation failure —
        // and every web request — falls through to the default rendering.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $code = PhoneNumber::errorCode($e->validator);

            if ($code === null) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $code,
                'errors' => $e->errors(),
            ], $e->status);
        });
    })->create();
