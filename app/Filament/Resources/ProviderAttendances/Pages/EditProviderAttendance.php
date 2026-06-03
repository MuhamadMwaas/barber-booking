<?php

namespace App\Filament\Resources\ProviderAttendances\Pages;

use App\Filament\Resources\ProviderAttendances\ProviderAttendanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderAttendance extends EditRecord
{
    protected static string $resource = ProviderAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
