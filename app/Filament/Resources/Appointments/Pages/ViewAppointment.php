<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAppointment extends ViewRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_invoice')
                ->label(__('resources.appointment.print_invoice'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): ?string => $this->record->invoice
                    ? route('invoice.print', ['invoice' => $this->record->invoice])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record->canPrintInvoice()),
            EditAction::make(),
        ];
    }
}
