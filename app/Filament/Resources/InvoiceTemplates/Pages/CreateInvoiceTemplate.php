<?php

namespace App\Filament\Resources\InvoiceTemplates\Pages;

use App\Filament\Resources\InvoiceTemplates\InvoiceTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceTemplate extends CreateRecord
{
    protected static string $resource = InvoiceTemplateResource::class;


    protected function afterCreate(): void
    {
        // Create default header lines
        $this->record->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 0,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.name',
                'font_size' => 14,
                'font_weight' => 'bold',
                'alignment' => 'center',
                'margin_bottom' => 3,
            ],
        ]);

        $this->record->lines()->create([
            'section' => 'header',
            'type' => 'separator',
            'order' => 1,
            'properties' => [
                'style' => 'solid',
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        $this->record->lines()->create([
            'section' => 'header',
            'type' => 'invoice_number',
            'order' => 2,
            'properties' => [
                'show_label' => true,
                'label' => 'Invoice No:',
                'alignment' => 'left',
            ],
        ]);

        $this->record->lines()->create([
            'section' => 'header',
            'type' => 'invoice_date',
            'order' => 3,
            'properties' => [
                'show_label' => true,
                'label' => 'Date:',
                'show_time' => true,
                'format' => 'd.m.Y H:i',
                'alignment' => 'left',
            ],
        ]);

        // Create default body lines
        $this->record->lines()->create([
            'section' => 'body',
            'type' => 'items_table',
            'order' => 0,
            'properties' => [
                'show_item_numbers' => true,
                'show_quantity' => true,
                'show_unit_price' => true,
                'show_tax_rate' => true,
                'show_total' => true,
                'table_border' => true,
                'row_separator' => true,
            ],
        ]);

        $this->record->lines()->create([
            'section' => 'body',
            'type' => 'totals_summary',
            'order' => 1,
            'properties' => [
                'show_subtotal' => true,
                'show_tax_breakdown' => true,
                'show_total' => true,
                'highlight_total' => true,
            ],
        ]);

        // Create default footer lines
        $this->record->lines()->create([
            'section' => 'footer',
            'type' => 'separator',
            'order' => 0,
            'properties' => [
                'style' => 'dashed',
            ],
        ]);

        $this->record->lines()->create([
            'section' => 'footer',
            'type' => 'payment_info',
            'order' => 1,
            'properties' => [
                'show_method' => true,
                'show_amount' => true,
            ],
        ]);

        $this->record->lines()->create([
            'section' => 'footer',
            'type' => 'thank_you_message',
            'order' => 2,
            'properties' => [
                'message' => 'Thank you for your business!',
                'font_style' => 'italic',
                'alignment' => 'center',
            ],
        ]);
    }
}
