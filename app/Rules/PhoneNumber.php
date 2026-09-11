<?php

namespace App\Rules;

use App\Services\Sms\PhoneNumberNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a phone number is a real, reachable international number
 * (AUTH-07).
 *
 * WHY THIS IS NOT A BARE REGEX
 *
 * The audit proposed `regex:/^\+[1-9]\d{7,14}$/` against the raw input. That
 * regex is the right *definition* of E.164 and the wrong *subject*: this
 * database deliberately stores numbers in human form — "+971-50-101-0101" is
 * the norm, see `config/sms.php` — and normalises only at the SMS gateway edge.
 * Applied to the raw string, the regex matches ZERO of the numbers currently
 * stored, so the first time an existing user pressed "save profile" with their
 * own unchanged number in the payload they would get a 422.
 *
 * So the rule normalises first and validates the result. Punctuation a human
 * added is not a security property; the digits underneath are.
 *
 * Reusing Services\Sms\PhoneNumberNormalizer is the point, not a convenience:
 * it means a number that passes validation is by construction a number the SMS
 * gateway can actually deliver an OTP to. A validator that accepted numbers the
 * gateway then rejected would just move the failure to where nobody sees it.
 *
 * (ThrottleKey does its own reduction on purpose — a throttle key must be
 * derivable from malformed input, which is exactly when it matters.)
 */
class PhoneNumber implements ValidationRule
{
    /**
     * E.164: a leading "+", a country code that cannot start with 0, and 8 to 15
     * digits in total.
     */
    private const E164 = '/^\+[1-9]\d{7,14}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.phone')->translate();

            return;
        }

        if (! self::isValid($value)) {
            $fail('validation.phone')->translate();
        }
    }

    public static function isValid(?string $phone): bool
    {
        return preg_match(self::E164, self::normalize($phone)) === 1;
    }

    /**
     * The canonical form of a number: "+" followed by digits only.
     *
     * Returns '' when the input carries no number at all, or when it is written
     * in national format ("050-101-0101") and `sms.default_country_code` is not
     * configured — in that case there is genuinely no way to know which country
     * the number belongs to, and guessing is how OTPs get texted to strangers.
     */
    public static function normalize(?string $phone): string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return '';
        }

        return app(PhoneNumberNormalizer::class)->normalize(
            $phone,
            config('sms.default_country_code')
        );
    }
}
