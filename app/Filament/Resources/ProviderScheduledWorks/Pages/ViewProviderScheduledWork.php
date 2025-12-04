<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderScheduledWork extends ViewRecord
{
    protected static string $resource = ProviderScheduledWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
