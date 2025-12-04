<?php

namespace App\Filament\Resources\SalonSettings\Pages;

use App\Filament\Resources\SalonSettings\SalonSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalonSetting extends EditRecord
{
    protected static string $resource = SalonSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

        protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

}
