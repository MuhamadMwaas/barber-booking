<?php

namespace App\Services\Sms;

/**
 * Turns a human-formatted phone number into the bare international form every
 * SMS gateway expects.
 *
 * This is not cosmetic. Numbers in this database are stored with separators —
 * "+971-50-101-0101" is the norm, not the exception — and seven.io rejects those
 * outright with code 202 (invalid recipient). Normalising at the gateway edge
 * means no other part of the app has to care.
 */
class PhoneNumberNormalizer
{
    /**
     * @param  string|null  $defaultCountryCode  Digits only, no "+". Used only for
     *                                           numbers written in national format
     *                                           with a single leading zero.
     */
    public function normalize(string $phone, ?string $defaultCountryCode = null): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        // A "+" only carries meaning in the first position; everything after it
        // that is not a digit is punctuation a human added for readability.
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return '+' . $digits;
        }

        // "00" is the international access prefix — the written form of "+".
        if (str_starts_with($digits, '00')) {
            return '+' . ltrim(substr($digits, 2), '0');
        }

        $defaultCountryCode = preg_replace('/\D+/', '', (string) $defaultCountryCode) ?: null;

        // A single leading zero is a national trunk prefix: it is dropped and
        // replaced by the country code. Without a configured country code we
        // cannot know which country, so the number is passed through unchanged
        // and the gateway account default decides.
        if (str_starts_with($digits, '0')) {
            return $defaultCountryCode === null
                ? $digits
                : '+' . $defaultCountryCode . ltrim($digits, '0');
        }

        // Already international, just without the "+" — seven.io accepts this,
        // but adding the "+" removes any ambiguity.
        return '+' . $digits;
    }

    /**
     * Same as normalize(), minus the leading "+".
     *
     * seven.io documents "+49171999999999", "49171999999999" and
     * "0049171999999999" as equivalent; the plain-digit form is the one its own
     * examples use, so it is what we put on the wire.
     */
    public function toGatewayFormat(string $phone, ?string $defaultCountryCode = null): string
    {
        return ltrim($this->normalize($phone, $defaultCountryCode), '+');
    }
}
