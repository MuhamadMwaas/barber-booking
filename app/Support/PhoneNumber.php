<?php

namespace App\Support;

/**
 * Comparison key for phone numbers typed by humans.
 *
 * The same person writes their number a different way every time —
 * `+49 176 1234567`, `0049-176-1234567`, `0176 1234567` — and the app stores
 * whatever they typed. String equality therefore treats one customer as three,
 * which is exactly how a guest could hold three overlapping bookings without the
 * duplicate guard ever noticing (BOOK-06).
 *
 * This produces a key for COMPARING two numbers. It is not a formatter, not a
 * validator, and must never be written back over what the customer entered — the
 * salon still has to be able to dial the number as given.
 */
class PhoneNumber
{
    /**
     * How many trailing digits decide that two numbers are the same person.
     *
     * Comparing the tail sidesteps the whole country-code problem: +49 176
     * 1234567, 0049 176 1234567 and 0176 1234567 differ only in their prefix, and
     * the subscriber part is identical. Nine is long enough that two real
     * customers colliding is implausible, and short enough to survive a missing
     * country code or a dropped trunk zero.
     */
    private const SIGNIFICANT_DIGITS = 9;

    /**
     * A comparison key, or null when the input cannot yield one.
     *
     * Returning null for blank or too-short input is deliberate: a null key must
     * never match another null key, or every guest without a phone number would
     * look like the same person.
     */
    public static function key(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Too short to identify anyone — a fragment, an extension, junk input.
        if (strlen($digits) < self::SIGNIFICANT_DIGITS) {
            return null;
        }

        return substr($digits, -self::SIGNIFICANT_DIGITS);
    }

    /**
     * Do these two numbers belong to the same person?
     *
     * False whenever either side has no usable key, so "no phone on file" never
     * matches "no phone on file".
     */
    public static function sameNumber(?string $a, ?string $b): bool
    {
        $keyA = self::key($a);

        return $keyA !== null && $keyA === self::key($b);
    }
}
