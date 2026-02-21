<?php

namespace App\Filament\Resources\PrinterSetting\Pages;

use App\Filament\Resources\PrinterSetting\PrinterSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrinterSettings extends ListRecords {
    protected static string $resource = PrinterSettingResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\CreateAction::make()
                ->label('Add Printer'),
        ];
    }
}
