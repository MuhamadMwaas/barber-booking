<?php

namespace App\Http\Requests;

use App\Enum\RegistrationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
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
            ],
            'password' => 'required|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        // `registration_method[]=email` makes input() return an array, and casting
        // an array to string raises "Array to string conversion" — which Laravel's
        // error handler promotes to an ErrorException, i.e. a 500 on an
        // unauthenticated endpoint. Normalise only what is actually a string and
        // let the `Rule::enum` in rules() reject everything else as a 422.
        $method = $this->input('registration_method');

        if (is_string($method)) {
            $this->merge([
                'registration_method' => strtolower($method),
            ]);
        }
    }
}
