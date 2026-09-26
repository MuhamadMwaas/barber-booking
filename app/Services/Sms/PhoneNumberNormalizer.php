<?php

namespace App\Services\Sms;

/**
 * Turns a human-formatted phone number into the bare international form every
 * SMS gateway expects.
 *
 * This is not cosmetic. seven.io rejects numbers with separators outright with
 * code 202 (invalid recipient). The API now stores users.phone already in this
 * form (see App\Rules\PhoneNumber::toE164()), but staff-entered numbers can still
 * carry separators, so the gateway edge keeps normalising too.
 */
class PhoneNumberNormalizer
{
    /**
     * Bare digits at least this long that already start with the default country
     * code are read as a full international number typed without its "+".
     *
     * "4915223917565" (13 digits) is +49 1522 3917565, not +49 4915223917565.
     * German numbers without their trunk zero are 10-11 digits long, so twelve is
     * the first length that cannot be one of them.
     */
    private const MIN_INTERNATIONAL_DIGITS = 12;

    /**
     * @param  string|null  $defaultCountryCode  Digits only, no "+". Used for
     *                                           numbers written in national format
     *                                           (a single leading zero) and for
     *                                           bare digits with no prefix at all.
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

        // No country configured: read the digits as already international, just
        // without the "+" — the behaviour gateways document for this form.
        if ($defaultCountryCode === null) {
            return '+' . $digits;
        }

        // The country code typed without its "+": "4915223917565".
        if (str_starts_with($digits, $defaultCountryCode) && strlen($digits) >= self::MIN_INTERNATIONAL_DIGITS) {
            return '+' . $digits;
        }

        // A national number whose trunk zero was dropped: "15223917565". Reading
        // it as international would make it +1 522… — a US number the OTP is
        // texted to and never arrives.
        return '+' . $defaultCountryCode . $digits;
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
