<?php

namespace App\Rules;

use App\Services\Sms\PhoneNumberNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates that a phone number is a real, reachable international number
 * (AUTH-07).
 *
 * WHY THIS IS NOT A BARE REGEX
 *
 * The audit proposed `regex:/^\+[1-9]\d{7,14}$/` against the raw input. That
 * regex is the right *definition* of E.164 and the wrong *subject*: users type
 * "0152 2391 7565", "+49-1522-3917565" and "0049 1522 3917565" for one number,
 * and the app must accept all of them.
 *
 * So the rule normalises first and validates the result. Punctuation a human
 * added is not a security property; the digits underneath are. Since the
 * German-prefix fix the API also STORES the normalised form — see toE164() and
 * App\Support\PhoneInput — so lookups and the unique check compare like with
 * like.
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

    /** Returned to the client when a phone number cannot be read as a real number. */
    public const ERROR_INVALID = 'INVALID_PHONE_NUMBER';

    /** Returned when the number already belongs to another account. */
    public const ERROR_TAKEN = 'PHONE_ALREADY_EXISTS';

    public static function isValid(?string $phone): bool
    {
        return preg_match(self::E164, self::normalize($phone)) === 1;
    }

    /**
     * The form users.phone is stored and looked up in ("+4915223917565"), or
     * null when the input is not a valid number.
     *
     * Every API path that accepts a phone passes it through here before it is
     * validated, stored or compared, so "015223917565", "0049 1522 3917565" and
     * "+49 1522 3917565" are one account, not three.
     */
    public static function toE164(mixed $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }

        $normalized = self::normalize($phone);

        return preg_match(self::E164, $normalized) === 1 ? $normalized : null;
    }

    /**
     * The machine-readable reason a validator rejected the phone field, or null
     * when the phone field did not fail (or failed only as "required").
     *
     * Read from failed() rather than from the message text, so the code stays
     * stable across languages and wording changes.
     */
    public static function errorCode(Validator $validator, string $field = 'phone'): ?string
    {
        $failed = $validator->failed()[$field] ?? [];

        return match (true) {
            array_key_exists(self::class, $failed) => self::ERROR_INVALID,
            array_key_exists('Unique', $failed) => self::ERROR_TAKEN,
            default => null,
        };
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
