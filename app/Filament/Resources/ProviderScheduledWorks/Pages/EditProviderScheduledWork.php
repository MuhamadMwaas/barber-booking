<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm;
use App\Models\ProviderScheduledWork;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class EditProviderScheduledWork extends EditRecord implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string $resource = ProviderScheduledWorkResource::class;

    /**
     * User ID for the provider — the single source of the provider's identity
     * for loading, saving and the title. Never read $this->record->user_id:
     * an unsaved record comes back from Livewire without its attributes.
     */
    #[Locked]
    public int $userId;

    /**
     * Form data
     */
    public ?array $data = [];

    /**
     * Mount the page
     */
    public function mount(int | string $record): void
    {
        // The list table is a table of providers, so the route key is always a user id.
        $provider = User::query()
            ->whereKey($record)
            ->whereHas('roles', fn ($query) => $query->where('name', 'provider'))
            ->first();

        abort_unless($provider, 404);

        $this->userId = $provider->id;

        parent::mount($record);

        $this->data = ProviderScheduledWorkForm::loadScheduleData($this->userId);
        // Hydrate the schema-backed form state
        $this->form->fill($this->data);
    }

    /**
     * Resolve record by provider user_id only. Never by schedule id: a schedule
     * row whose id equals this user id belongs to a different provider.
     */
    protected function resolveRecord(int|string $key): ProviderScheduledWork
    {
        return ProviderScheduledWork::where('user_id', $key)->first()
            ?? new ProviderScheduledWork(['user_id' => $key]);
    }

    /**
     * A provider without shifts yet has an unsaved record. The parent nulls it
     * after every action (e.g. repeater add/delete), and Livewire skips restoring
     * null into the typed $record, leaving it uninitialized on the next request.
     * Keep the unsaved record instead; this page has no Create/Edit actions whose
     * events the parent chain would dispatch.
     */
    protected function afterActionCalled(Action $action): void
    {
        if (! $this->getRecord()->exists) {
            return;
        }

        parent::afterActionCalled($action);
    }

    /**
     * Configure the schema
     */
    public function getFormSchema(): Schema
    {
        return ProviderScheduledWorkForm::configure(Schema::make(), $this->userId)
            ->statePath('data');
    }

    /**
     * Get page title
     */
    public function getTitle(): string|Htmlable
    {
        $user = User::find($this->userId);
        return __('resources.provider_scheduled_work.edit_schedule_for', [
            'name' => $user?->full_name ?? __('resources.provider_scheduled_work.provider')
        ]);
    }

    /**
     * Get page heading
     */
    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    /**
     * Get subheading
     */
    public function getSubheading(): string|Htmlable|null
    {
        return __('resources.provider_scheduled_work.edit_weekly_schedule_description');
    }

    /**
     * Header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('resources.provider_scheduled_work.back_to_list'))
                ->icon('heroicon-o-arrow-left')
                ->url(ProviderScheduledWorkResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    /**
     * Get form actions
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('resources.provider_scheduled_work.save_schedule'))
                ->icon('heroicon-o-check')
                ->action('saveSchedule')
                ->color('primary'),

            Action::make('cancel')
                ->label(__('resources.provider_scheduled_work.cancel'))
                ->icon('heroicon-o-x-mark')
                ->url(ProviderScheduledWorkResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    /**
     * Save the schedule
     */
    public function saveSchedule(): void
    {
        // Validate the data
        $errors = ProviderScheduledWorkForm::validateSchedule($this->data);

        if (!empty($errors)) {
            foreach ($errors as $message) {
                Notification::make()
                    ->danger()
                    ->title(__('resources.provider_scheduled_work.validation_error'))
                    ->body($message)
                    ->send();
            }
            return;
        }

        try {
            // Save the schedule
            ProviderScheduledWorkForm::saveScheduleData($this->userId, $this->data);

            // Success notification
            Notification::make()
                ->success()
                ->title(__('resources.provider_scheduled_work.schedule_saved'))
                ->body(__('resources.provider_scheduled_work.schedule_saved_successfully'))
                ->send();

            // Redirect to index
            $this->redirect(ProviderScheduledWorkResource::getUrl('index'));

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title(__('resources.provider_scheduled_work.save_error'))
                ->body($e->getMessage())
                ->send();
        }
    }
}
