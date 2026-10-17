<?php

namespace App\Notifications\Params;

use App\Notifications\contract\NotificationParamResolver;
use Illuminate\Support\Carbon;

/**
 * A date rendered in the reader's language: ['type' => 'date', 'value' => '2026-09-28', 'format' => 'l'].
 *
 * Needed because one params array is rendered in several locales — a push goes
 * out in en/ar/de at once, and the in-app list re-translates on every read — so
 * a weekday frozen as a string ("Montag") would be wrong for every other reader.
 */
class DateParamResolver implements NotificationParamResolver
{
    public function resolve(array $data, string $locale, array $config = []): mixed
    {
        $value = $data['value'] ?? null;

        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)
            ->locale($locale)
            ->translatedFormat($data['format'] ?? 'd.m.Y');
    }
}
