<?php

namespace App\Filament\Resources\ProviderAttendances\Pages;

use App\Filament\Resources\ProviderAttendances\ProviderAttendanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderAttendance extends ViewRecord
{
    protected static string $resource = ProviderAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
