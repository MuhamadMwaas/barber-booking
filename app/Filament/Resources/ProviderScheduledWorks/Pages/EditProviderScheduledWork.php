<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm;
use App\Models\ProviderScheduledWork;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EditProviderScheduledWork extends EditRecord implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string $resource = ProviderScheduledWorkResource::class;

    /**
     * User ID for the provider
     */
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
        parent::mount($record);

        // Prefer the resolved record's user_id; fallback to the route param as user id.
        $this->userId = $record ?? (int) $record;
        $this->data = ProviderScheduledWorkForm::loadScheduleData($this->userId);
        // Hydrate the schema-backed form state
        $this->form->fill($this->data);
    }

    /**
     * Resolve record: try schedule ID first; if not found, treat param as provider user_id.
     */
    protected function resolveRecord(int|string $key): ProviderScheduledWork
    {
        $byId = ProviderScheduledWork::find($key);
        if ($byId) {
            return $byId;
        }

        return ProviderScheduledWork::where('user_id', $key)->first()
            ?? new ProviderScheduledWork(['user_id' => $key]);
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
        $user = \App\Models\User::find($this->userId);
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
            ProviderScheduledWorkForm::saveScheduleData($this->record->user_id, $this->data);

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
