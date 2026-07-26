<?php

namespace App\Http\Requests;

use App\Enum\RegistrationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Step 3 of the reset flow: commit the new password.
 *
 * Two accepted shapes:
 *   - preferred — `reset_token` obtained from POST /auth/password/verify-otp
 *   - legacy    — `registration_method` + `email|phone` + `otp` in one shot,
 *                 kept so existing clients keep working unchanged.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $legacy = fn () => !$this->filled('reset_token');

        return [
            'reset_token' => ['required_without:otp', 'nullable', 'string'],

            'otp' => ['required_without:reset_token', 'nullable', 'string', 'max:10'],
            'registration_method' => [Rule::requiredIf($legacy), 'nullable', Rule::enum(RegistrationMethod::class)],
            'email' => [
                Rule::requiredIf(fn () => $legacy() && $this->input('registration_method') === RegistrationMethod::EMAIL->value),
                'nullable',
                'email',
            ],
            'phone' => [
                Rule::requiredIf(fn () => $legacy() && $this->input('registration_method') === RegistrationMethod::PHONE->value),
                'nullable',
                'string',
                'max:20',
            ],

            // Same strength contract as registration — a reset must not be a way
            // to downgrade an account to a weaker password than signup allows.
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols(),
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

    public function usesGrantToken(): bool
    {
        return $this->filled('reset_token');
    }

    public function registrationMethod(): RegistrationMethod
    {
        return RegistrationMethod::from((string) $this->input('registration_method'));
    }

    public function identifier(): string
    {
        return $this->registrationMethod() === RegistrationMethod::PHONE
            ? trim((string) $this->input('phone'))
            : trim((string) $this->input('email'));
    }
}
