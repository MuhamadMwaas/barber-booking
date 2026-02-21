<?php

namespace App\Filament\Resources\InvoiceTemplates\Pages;

use App\Filament\Resources\InvoiceTemplates\InvoiceTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoiceTemplate extends ViewRecord
{
    protected static string $resource = InvoiceTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
