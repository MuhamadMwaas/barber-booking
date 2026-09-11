<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordRequirements implements ValidationRule
{
    public const MIN_LENGTH = 9;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (mb_strlen($value) < self::MIN_LENGTH) {
            $fail(__('passwords.requirements.validation.minimum_length', [
                'min' => self::MIN_LENGTH,
            ]));
        }

        // Printable ASCII keeps every letter inside A-Z / a-z while still
        // allowing numbers and symbols. Whitespace and non-Latin scripts are
        // intentionally rejected.
        if (preg_match('/^[\x21-\x7E]+$/D', $value) !== 1) {
            $fail(__('passwords.requirements.validation.latin_only'));
        }

        if (preg_match('/[A-Z]/', $value) !== 1) {
            $fail(__('passwords.requirements.validation.uppercase'));
        }

        if (preg_match('/[0-9]/', $value) !== 1) {
            $fail(__('passwords.requirements.validation.number'));
        }
    }
}
