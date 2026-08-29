<?php

use App\Http\Middleware\EnforceJsonAcceptHeader;
use App\Http\Middleware\EnsureEmailIsVerifiedViaOtp;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocaleFromSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        //
    })->create();
