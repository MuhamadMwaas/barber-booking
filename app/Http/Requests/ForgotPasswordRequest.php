<?php

namespace App\Http\Requests;

use App\Enum\RegistrationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 1 of the reset flow: pick a channel and a destination.
 * Mirrors LoginRequest so the mobile client can reuse the same payload shape.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_method' => ['required', Rule::enum(RegistrationMethod::class)],
            'email' => [
                Rule::requiredIf(fn () => $this->input('registration_method') === RegistrationMethod::EMAIL->value),
                'nullable',
                'email',
            ],
            'phone' => [
                Rule::requiredIf(fn () => $this->input('registration_method') === RegistrationMethod::PHONE->value),
                'nullable',
                'string',
                'max:20',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('registration_method')) {
            $this->merge([
                'registration_method' => strtolower((string) $this->input('registration_method')),
            ]);
        }
    }

    public function registrationMethod(): RegistrationMethod
    {
        return RegistrationMethod::from((string) $this->input('registration_method'));
    }

    /** The email address or phone number the reset code should go to. */
    public function identifier(): string
    {
        return $this->registrationMethod() === RegistrationMethod::PHONE
            ? trim((string) $this->input('phone'))
            : trim((string) $this->input('email'));
    }
}
