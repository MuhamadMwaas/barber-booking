<?php

namespace App\Filament\Resources\SalonSettings\Pages;

use App\Filament\Resources\SalonSettings\SalonSettingResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalonSetting extends EditRecord
{
    protected static string $resource = SalonSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('resources.salon_setting.updated_notification');
    }
}
