<?php

namespace App\Filament\Forms\Components;

use App\Rules\PasswordRequirements;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class PasswordInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->password()
            ->revealable()
            ->dehydrateStateUsing(fn ($state): string => Hash::make((string) $state))
            // Blank on edit means "keep the current password". Leaving the state
            // null also keeps PasswordRequirements from running: it is not an
            // implicit rule, so Laravel skips it for a null value.
            ->dehydrated(fn ($state): bool => filled($state))
            ->rules([
                'string',
                new PasswordRequirements,
            ])
            ->autocomplete('new-password')
            ->maxLength(255)
            ->confirmed()
            ->extraInputAttributes([
                'dir' => 'ltr',
                // The checklist below is driven straight off the keystroke so it
                // stays live without a Livewire round trip. `id` scopes the event:
                // the listener is on window, so a modal opened over a page that
                // also has a password field would otherwise drive both panels.
                'x-init' => '$nextTick(() => $dispatch(\'password-requirements-updated\', { id: $el.id, value: $el.value }))',
                'x-on:input' => '$dispatch(\'password-requirements-updated\', { id: $el.id, value: $event.target.value })',
            ])
            // Seeds the checklist. belowContent() is overridden below, so this
            // survives a later helperText() call by the caller.
            ->belowContent(null);
    }

    /**
     * Filament's belowContent() is a plain overwrite, and helperText() is built on
     * top of it — so `PasswordInput::make(...)->helperText(...)` silently replaced
     * the requirements checklist, which is why it rendered on no page at all. Pin
     * the checklist ahead of whatever the caller puts below the field instead.
     *
     * @param  array<Component | Action | ActionGroup | string | Htmlable> | Schema | Component | Action | ActionGroup | string | Htmlable | Closure | null  $components
     */
    public function belowContent(array|Schema|Component|Action|ActionGroup|string|Htmlable|Closure|null $components): static
    {
        return parent::belowContent(fn (Component $component): array => [
            View::make('filament.forms.components.password-requirements')
                ->viewData([
                    'minimumLength' => PasswordRequirements::MIN_LENGTH,
                    'inputId' => $component->getId(),
                ]),
            ...array_filter(Arr::wrap($component->evaluate($components))),
        ]);
    }
}
