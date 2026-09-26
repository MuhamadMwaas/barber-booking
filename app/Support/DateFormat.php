<?php

namespace App\Support;

/**
 * Locale-aware PHP date() formats for everything the admin panel displays.
 *
 * German staff read dates day-first ("30/12/2026") and times on the 24-hour
 * clock ("14:30"). Locales without an entry here keep whatever format the
 * caller passes as the fallback, so turning this on changes nothing for en/ar.
 *
 * Every method is meant to be called lazily (inside a closure) so it sees the
 * locale of the request being rendered, not the one active at boot.
 *
 * Filament's global defaults are wired in AppServiceProvider::registerFilamentDateFormats();
 * columns with a hard-coded format must call these helpers themselves.
 */
final class DateFormat
{
    private const FORMATS = [
        'de' => [
            'date' => 'd/m/Y',
            'date_time' => 'd/m/Y H:i',
            'date_time_seconds' => 'd/m/Y H:i:s',
            'time' => 'H:i',
            'time_seconds' => 'H:i:s',
        ],
    ];

    public static function date(string $fallback = 'Y-m-d'): string
    {
        return self::resolve('date', $fallback);
    }

    public static function dateTime(string $fallback = 'Y-m-d H:i'): string
    {
        return self::resolve('date_time', $fallback);
    }

    public static function dateTimeWithSeconds(string $fallback = 'Y-m-d H:i:s'): string
    {
        return self::resolve('date_time_seconds', $fallback);
    }

    public static function time(string $fallback = 'H:i'): string
    {
        return self::resolve('time', $fallback);
    }

    public static function timeWithSeconds(string $fallback = 'H:i:s'): string
    {
        return self::resolve('time_seconds', $fallback);
    }

    private static function resolve(string $key, string $fallback): string
    {
        return self::FORMATS[app()->getLocale()][$key] ?? $fallback;
    }
}
