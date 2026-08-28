<x-filament-panels::page>

    {{-- The whole editor is one schema; header actions provide Save, the two
         preview links, and a manual cache flush. --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check" size="lg">
                {{ __('landing.save') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />

</x-filament-panels::page>
