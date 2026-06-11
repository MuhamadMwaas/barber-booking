<?php

namespace App\Http\Requests;

use App\Enum\RegistrationMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name'=> 'required|string|max:255',
            'registration_method' => ['required', Rule::enum(RegistrationMethod::class)],
            'email' => [
                Rule::requiredIf(fn () => $this->input('registration_method') === RegistrationMethod::EMAIL->value),
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'phone' => [
                Rule::requiredIf(fn () => $this->input('registration_method') === RegistrationMethod::PHONE->value),
                'nullable',
                'string',
                'max:255',
                'unique:users,phone',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::min(8)
                    ->mixedCase()->letters()->numbers()->symbols()
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

        // Resolve the locale up-front so the duplicate-email response built in
        // failedValidation() comes back in the caller's language (ar/de/en).
        $this->applyRequestLocale();
    }

    /**
     * Intercept the duplicate-email case and return a dedicated, machine-readable
     * 409 Conflict response so the mobile app can show its "account already
     * exists" dialog (with Login / Close buttons) instead of routing the user to
     * the OTP screen. Every other validation failure keeps the default 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        $emailFailures = $validator->failed()['email'] ?? [];

        if (array_key_exists('Unique', $emailFailures)) {
            throw new HttpResponseException(response()->json([
                'success'    => false,
                'error_code' => 'EMAIL_ALREADY_EXISTS',
                'title'      => __('auth.email_exists_title'),
                'message'    => __('auth.email_exists_body'),
            ], 409));
        }

        parent::failedValidation($validator);
    }

    /**
     * Honour an explicit `locale` field, then the `Accept-Language` header,
     * falling back to the app default. Mirrors AuthController::applyRequestLocale().
     */
    private function applyRequestLocale(): void
    {
        $supported = ['de', 'ar', 'en'];

        $locale = $this->input('locale');

        if (! $locale) {
            $header = (string) $this->header('Accept-Language', '');
            $primary = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));
            $locale = $primary ?: null;
        }

        if ($locale && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }
    }
}
