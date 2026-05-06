<?php

namespace App\Http\Requests;

use App\Enum\RegistrationMethod;
use Illuminate\Foundation\Http\FormRequest;
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
    }
}
