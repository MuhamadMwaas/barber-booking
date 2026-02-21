<?php

namespace App\Filament\Resources\PrinterSetting\Pages;

use App\Filament\Resources\PrinterSetting\PrinterSettingResource;
use App\Models\PrinterSetting;
use Filament\Resources\Pages\CreateRecord;

class CreatePrinterSetting extends CreateRecord {
    protected static string $resource = PrinterSettingResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string {
        return 'Printer added successfully';
    }

    protected function mutateFormDataBeforeCreate(array $data): array {
        if (PrinterSetting::count() === 0) {
            $data['is_default'] = true;
        }

        return $data;
    }
}
