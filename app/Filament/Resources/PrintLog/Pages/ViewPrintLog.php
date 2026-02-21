<?php

namespace App\Filament\Resources\PrintLog\Pages;

use App\Filament\Resources\PrintLog\PrintLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPrintLog extends ViewRecord {
    protected static string $resource = PrintLogResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\Action::make('reprint')
                ->label('Reprint Invoice')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->url(fn(): string => route('invoice.print', $this->record->invoice_id))
                ->openUrlInNewTab()
                ->visible(fn(): bool => $this->record->invoice !== null),
        ];
    }
}
