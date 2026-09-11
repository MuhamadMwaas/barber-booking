<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;

/**
 * The partner field for {@see PasswordInput}. Laravel's `confirmed` rule looks
 * for `<field>_confirmation` alongside the field it guards, so the name must
 * keep that suffix.
 */
class PasswordConfirmationInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->password()
            ->revealable()
            ->dehydrated(false)
            ->autocomplete('new-password')
            ->maxLength(255)
            // Matches PasswordInput: passwords are Latin-only, so the field
            // stays left-to-right even on the Arabic panel.
            ->extraInputAttributes(['dir' => 'ltr']);
    }
}
