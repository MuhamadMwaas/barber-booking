<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Derives the "which account is this request aimed at?" throttle key (AUTH-01).
 *
 * Rate limiting an auth endpoint on the source IP alone protects nobody in
 * particular: the attacker chooses their address, and with enough of them every
 * per-IP bucket stays empty. What an attacker cannot choose is WHICH account
 * they are attacking — so the identifier in the request body is the dimension
 * that actually holds under a distributed attack.
 *
 * Every method here is total: it never throws and never returns an empty key for
 * a caller to accidentally group unrelated requests under. When the identifier
 * cannot be read (missing, an array, blank), the caller must fall back to the IP
 * dimension rather than letting the request through unlimited.
 */
final class ThrottleKey
{
    /**
     * Hashed, normalised key for the account this request targets, or null when
     * the request carries no usable identifier.
     *
     * Normalisation matters for correctness, not tidiness: without it
     * `Victim@Example.com` and `victim@example.com` get separate buckets and the
     * limit is trivially doubled by varying the case.
     */
    public static function forIdentifier(Request $request): ?string
    {
        $identifier = self::rawIdentifier($request);

        if ($identifier === null) {
            return null;
        }

        // Hashed because the cache store is the database (CACHE_STORE=database):
        // an un-hashed key would write every customer's email and phone number
        // into the `cache` table in clear text, where nothing expects to find PII.
        return hash('sha256', $identifier);
    }

    /**
     * The normalised identifier before hashing. Exposed separately so tests can
     * assert the normalisation rules without reversing a hash.
     */
    public static function rawIdentifier(Request $request): ?string
    {
        $method = $request->input('registration_method');
        $method = is_string($method) ? strtolower(trim($method)) : null;

        // The auth endpoints select their identity field with `registration_method`.
        // When it is absent (verify-email-otp merges it in later, some clients omit
        // it) fall back to whichever field is actually present.
        $value = match ($method) {
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            default => $request->input('email') ?? $request->input('phone'),
        };

        // `?email[]=a&email[]=b` makes input() return an array. Passing that to a
        // string function is a TypeError, i.e. a 500 on an unauthenticated
        // endpoint — the limiter must never be the thing that breaks the request.
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Phone numbers arrive formatted in many ways (+49 30 123, 0049-30-123).
        // Reducing to digits and a leading + keeps one bucket per real number.
        // Deliberately NOT using Services\Sms\PhoneNumberNormalizer: that class may
        // reject input it considers invalid, and a throttle key must be derivable
        // from malformed input too — that is precisely when it is under attack.
        $normalised = str_contains($value, '@')
            ? mb_strtolower($value)
            : (string) preg_replace('/(?!^\+)\D/', '', $value);

        return $normalised === '' ? null : $normalised;
    }
}
