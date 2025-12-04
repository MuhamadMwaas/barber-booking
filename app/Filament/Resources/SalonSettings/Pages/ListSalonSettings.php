<?php

namespace App\Filament\Resources\SalonSettings\Pages;

use App\Filament\Resources\SalonSettings\SalonSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalonSettings extends ListRecords
{
    protected static string $resource = SalonSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
