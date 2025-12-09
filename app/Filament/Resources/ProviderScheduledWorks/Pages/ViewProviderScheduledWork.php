<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Pages\ManageProviderSchedules;
use App\Filament\Pages\ViewProviderScheduleTimeline;
use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderScheduledWork extends ViewRecord
{
    protected static string $resource = ProviderScheduledWorkResource::class;

    /**
     * Pre-fill the form data with the provider's user_id for the timeline
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add the user_id to the form data so the timeline component can access it
        $data['selected_user_id'] = $this->record->user_id;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manage_full_schedule')
                ->label(__('resources.provider_scheduled_work.manage_schedule'))
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->url(fn () => ManageProviderSchedules::getUrl(['userId' => $this->record->user_id]))
                ->tooltip(__('resources.provider_scheduled_work.manage_schedule_tooltip')),
        ];
    }
}
