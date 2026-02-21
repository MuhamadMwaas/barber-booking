<?php

namespace App\Filament\Resources\InvoiceTemplates\Pages;

use App\Filament\Resources\InvoiceTemplates\InvoiceTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceTemplates extends ListRecords
{
    protected static string $resource = InvoiceTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
