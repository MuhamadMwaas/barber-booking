<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderScheduledWorks extends ListRecords
{
    protected static string $resource = ProviderScheduledWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
