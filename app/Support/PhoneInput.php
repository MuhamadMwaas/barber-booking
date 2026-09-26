<?php

namespace App\Support;

use App\Rules\PhoneNumber;
use Illuminate\Http\Request;

/**
 * Rewrites the phone field of an incoming API request into the one form
 * users.phone is stored in ("+4915223917565").
 *
 * Called before validation, so that `unique:users,phone`, `exists:users,phone`
 * and every `where('phone', …)` lookup compare the stored value against the same
 * spelling — without it "015223917565" and "+49 1522 3917565" register as two
 * accounts, and a user who signed up with one cannot log in with the other.
 *
 * Input that is not a valid number is left exactly as sent: the PhoneNumber rule
 * (or the lookup) then rejects it, and the error shows what the user typed.
 */
final class PhoneInput
{
    public static function canonicalize(Request $request, string $field = 'phone'): void
    {
        $canonical = PhoneNumber::toE164($request->input($field));

        if ($canonical !== null) {
            $request->merge([$field => $canonical]);
        }
    }
}
