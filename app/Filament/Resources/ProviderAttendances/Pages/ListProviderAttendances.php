<?php

namespace App\Filament\Resources\ProviderAttendances\Pages;

use App\Filament\Resources\ProviderAttendances\ProviderAttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderAttendances extends ListRecords
{
    protected static string $resource = ProviderAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
