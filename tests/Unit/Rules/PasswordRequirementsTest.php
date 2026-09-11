<?php

namespace Tests\Unit\Rules;

use App\Filament\Forms\Components\PasswordInput;
use App\Rules\PasswordRequirements;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordRequirementsTest extends TestCase
{
    public function test_it_accepts_passwords_matching_the_shared_policy(): void
    {
        $this->assertPasswordIsValid('ABCDEFGH1');
        $this->assertPasswordIsValid('Abcdefgh1');
        $this->assertPasswordIsValid('Abcdefg1!');
    }

    public function test_it_rejects_passwords_shorter_than_nine_characters(): void
    {
        $this->assertPasswordIsInvalid('Abcdefg1');
    }

    public function test_it_rejects_non_ascii_characters_and_spaces(): void
    {
        $this->assertPasswordIsInvalid('Abcdefg1ع');
        $this->assertPasswordIsInvalid('Abc defg1');
        $this->assertPasswordIsInvalid('Abcdefg1é');
    }

    public function test_it_requires_an_uppercase_letter(): void
    {
        $this->assertPasswordIsInvalid('abcdefgh1');
    }

    public function test_it_requires_a_number(): void
    {
        $this->assertPasswordIsInvalid('Abcdefghi');
    }

    public function test_the_filament_view_exposes_all_live_requirements(): void
    {
        $html = view('filament.forms.components.password-requirements', [
            'minimumLength' => PasswordRequirements::MIN_LENGTH,
        ])->render();

        $this->assertStringContainsString('password-requirements-updated.window', $html);
        $this->assertStringContainsString('hasMinimumLength()', $html);
        $this->assertStringContainsString('hasLatinCharactersOnly()', $html);
        $this->assertStringContainsString('hasUppercase()', $html);
        $this->assertStringContainsString('hasNumber()', $html);
    }

    public function test_the_shared_filament_input_is_configured_for_new_passwords(): void
    {
        $field = PasswordInput::make('password');

        $this->assertTrue($field->isPassword());
        $this->assertTrue($field->isPasswordRevealable());
        $this->assertSame('new-password', $field->getAutocomplete());
        $this->assertSame(255, $field->getMaxLength());

        $rules = $field->getValidationRules();

        $this->assertContains('string', $rules);
        $this->assertContains('confirmed', $rules);
        $this->assertTrue(collect($rules)->contains(
            fn ($rule): bool => $rule instanceof PasswordRequirements,
        ));

        $attributes = $field->getExtraInputAttributeBag()->getAttributes();

        $this->assertSame('ltr', $attributes['dir']);
        $this->assertArrayHasKey('x-init', $attributes);
        $this->assertArrayHasKey('x-on:input', $attributes);
    }

    private function assertPasswordIsValid(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', new PasswordRequirements]],
        );

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    private function assertPasswordIsInvalid(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', new PasswordRequirements]],
        );

        $this->assertTrue($validator->fails(), "[{$password}] unexpectedly passed validation.");
        $this->assertNotEmpty($validator->errors()->get('password'));
    }
}
