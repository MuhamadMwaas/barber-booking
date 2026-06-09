<?php

namespace Database\Seeders;

use App\Models\InvoiceTemplate;
use Illuminate\Database\Seeder;

class InvoiceTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // German Template - Matching the uploaded image
        $this->createGermanTemplate();

        // English Template
        $this->createEnglishTemplate();

        // Compact 58mm Template
        // $this->createCompact58mmTemplate();
    }

    /**
     * Create German template matching the uploaded image
     */
    protected function createGermanTemplate(): void
    {
        $template = InvoiceTemplate::updateOrCreate(
            ['name' => 'German POS Receipt (80mm)'],
            [
            'name' => 'German POS Receipt (80mm)',
            'description' => 'German receipt template matching Look up Friseur style',
            'is_active' => true,
            'is_default' => true,
            'language' => 'de',
            'paper_size' => '80mm',
            'paper_width' => 80,
            'font_family' => 'Arial',
            'font_size' => 10,
            'global_styles' => [
                'primary_color' => '#000000',
                'secondary_color' => '#666666',
                'line_height' => 1.2,
                'padding' => 5,
                'border_color' => '#cccccc',
            ],
            'company_info' => [
                'name' => 'Look up Friseur',
                'address' => "Rupprechtstr. 33\nD-84034 Landshut",
                'phone' => '0871-6877271',
                'tax_number' => 'St.Nr.132/167/54659',
                'email' => '',
                'logo_path' => null,
            ],
        ]);

        // Clean re-seed: wipe existing lines before rebuilding them (no duplicates).
        $template->lines()->delete();

        // Header Lines
        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 0,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.name',
                'font_size' => 12,
                'font_weight' => 'bold',
                'alignment' => 'center',
                'margin_bottom' => 2,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 1,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.address',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 2,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.phone',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 3,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.tax_number',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'separator',
            'order' => 4,
            'properties' => [
                'style' => 'solid',
                'width' => 1,
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ]);

        // Invoice Number (DYNAMIC) — real number + copy label (German, reprints only)
        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 5,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'invoice.number_with_copy',
                'prefix' => 'Rechnung Nr. ',
                'font_size' => 11,
                'font_weight' => 'bold',
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'two_column',
            'order' => 6,
            'properties' => [
                'label' => 'Datum:',
                'label_width' => 30,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.date',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'separator',
            'order' => 7,
            'properties' => [
                'style' => 'dashed',
                'width' => 1,
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ]);

        // Body - Items Table
        $template->lines()->create([
            'section' => 'body',
            'type' => 'items_table',
            'order' => 0,
            'properties' => [
                'show_item_numbers' => true,
                'show_quantity' => false,
                'show_unit_price' => true,
                'show_tax_rate' => false,
                'show_tax_amount' => false,
                'show_total' => true,
                'table_border' => false,
                'header_background' => '#ffffff',
                'header_text_color' => '#000000',
                'row_separator' => true,
                'font_size' => 9,
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'separator',
            'order' => 1,
            'properties' => [
                'style' => 'dashed',
                'width' => 1,
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        // Items total (pre-discount) — only shows when a discount was granted.
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 2,
            'properties' => [
                'label' => 'Artikel gesamt',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.items_total',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 1,
                'hide_when_empty' => true,
            ],
        ]);

        // Discount line — only shows when a discount was granted.
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 3,
            'properties' => [
                'label' => 'Rabatt',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.discount',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 2,
                'hide_when_empty' => true,
            ],
        ]);

        // Tax Breakdown (DYNAMIC — calculated from the actual invoice, not hard-coded)
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 4,
            'properties' => [
                'label' => 'Netto',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.subtotal',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 5,
            'properties' => [
                'label' => '+ 19,0% MwSt.',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.tax_amount',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'separator',
            'order' => 6,
            'properties' => [
                'style' => 'double',
                'width' => 2,
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        // Grand Total
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 7,
            'properties' => [
                'label' => 'Summe Eur',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.total',
                'font_size' => 11,
                'label_bold' => true,
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'separator',
            'order' => 8,
            'properties' => [
                'style' => 'double',
                'width' => 2,
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        // Payment
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 9,
            'properties' => [
                'label' => 'Gegeben Eur',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'payment.amount',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 2,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'text',
            'order' => 10,
            'properties' => [
                'content_type' => 'static',
                'static_value' => 'Bezahlt per Girocard',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        // Footer
        $template->lines()->create([
            'section' => 'footer',
            'type' => 'separator',
            'order' => 0,
            'properties' => [
                'style' => 'dashed',
                'width' => 1,
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'thank_you_message',
            'order' => 1,
            'properties' => [
                'message' => 'Danke für Ihren Besuch in unserem Zentrum',
                'font_size' => 10,
                'font_style' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'text',
            'order' => 2,
            'properties' => [
                'content_type' => 'static',
                'static_value' => 'Es bediente Sie:',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'text',
            'order' => 3,
            'properties' => [
                'content_type' => 'static',
                'static_value' => 'Luay',
                'font_size' => 10,
                'font_weight' => 'bold',
                'alignment' => 'center',
                'margin_bottom' => 5,
            ],
        ]);

        // TSE/Fiskaly Info
        $template->lines()->create([
            'section' => 'footer',
            'type' => 'tse_info',
            'order' => 4,
            'properties' => [
                'show_tss_serial' => true,
                'show_transaction_number' => true,
                'show_signature_counter' => true,
                'show_timestamp' => true,
                'font_size' => 7,
                'alignment' => 'center',
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ]);

        // QR Code
        $template->lines()->create([
            'section' => 'footer',
            'type' => 'qr_code',
            'order' => 5,
            'properties' => [
                'size' => 120,
                'alignment' => 'center',
                'error_correction' => 'M',
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ]);
    }

    /**
     * Create English template matching the German POS receipt layout
     */
    protected function createEnglishTemplate(): void
    {
        $template = InvoiceTemplate::updateOrCreate(
            ['name' => 'English POS Receipt (80mm)'],
            [
            'name' => 'English POS Receipt (80mm)',
            'description' => 'English receipt template matching Look up Friseur style',
            'is_active' => true,
            'is_default' => false,
            'language' => 'en',
            'paper_size' => '80mm',
            'paper_width' => 80,
            'font_family' => 'Arial',
            'font_size' => 10,
            'global_styles' => [
                'primary_color' => '#000000',
                'secondary_color' => '#666666',
                'line_height' => 1.2,
                'padding' => 5,
                'border_color' => '#cccccc',
            ],
            'company_info' => [
                'name' => 'Look up Friseur',
                'address' => "Rupprechtstr. 33\nD-84034 Landshut",
                'phone' => '0871-6877271',
                'tax_number' => 'Tax No. 132/167/54659',
                'email' => '',
                'logo_path' => null,
            ],
        ]);

        // Clean re-seed: wipe existing lines before rebuilding them (no duplicates).
        $template->lines()->delete();

        // Header Lines
        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 0,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.name',
                'font_size' => 12,
                'font_weight' => 'bold',
                'alignment' => 'center',
                'margin_bottom' => 2,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 1,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.address',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 2,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.phone',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 3,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.tax_number',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'separator',
            'order' => 4,
            'properties' => [
                'style' => 'solid',
                'width' => 1,
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ]);

        // Invoice Number (DYNAMIC) — real number + copy label (reprints only)
        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 5,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'invoice.number_with_copy',
                'prefix' => 'Invoice No. ',
                'font_size' => 11,
                'font_weight' => 'bold',
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'two_column',
            'order' => 6,
            'properties' => [
                'label' => 'Date:',
                'label_width' => 30,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.date',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'separator',
            'order' => 7,
            'properties' => [
                'style' => 'dashed',
                'width' => 1,
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ]);

        // Body - Items Table
        $template->lines()->create([
            'section' => 'body',
            'type' => 'items_table',
            'order' => 0,
            'properties' => [
                'show_item_numbers' => true,
                'show_quantity' => false,
                'show_unit_price' => true,
                'show_tax_rate' => false,
                'show_tax_amount' => false,
                'show_total' => true,
                'table_border' => false,
                'header_background' => '#ffffff',
                'header_text_color' => '#000000',
                'row_separator' => true,
                'font_size' => 9,
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'separator',
            'order' => 1,
            'properties' => [
                'style' => 'dashed',
                'width' => 1,
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        // Items total (pre-discount) — only shows when a discount was granted.
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 2,
            'properties' => [
                'label' => 'Items total',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.items_total',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 1,
                'hide_when_empty' => true,
            ],
        ]);

        // Discount line — only shows when a discount was granted.
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 3,
            'properties' => [
                'label' => 'Discount',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.discount',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 2,
                'hide_when_empty' => true,
            ],
        ]);

        // Tax Breakdown (DYNAMIC — calculated from the actual invoice, not hard-coded)
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 4,
            'properties' => [
                'label' => 'Net',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.subtotal',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 5,
            'properties' => [
                'label' => '+ 19.0% VAT',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.tax_amount',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'separator',
            'order' => 6,
            'properties' => [
                'style' => 'double',
                'width' => 2,
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        // Grand Total
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 7,
            'properties' => [
                'label' => 'Total Eur',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'invoice.total',
                'font_size' => 11,
                'label_bold' => true,
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'separator',
            'order' => 8,
            'properties' => [
                'style' => 'double',
                'width' => 2,
                'margin_top' => 2,
                'margin_bottom' => 2,
            ],
        ]);

        // Payment
        $template->lines()->create([
            'section' => 'body',
            'type' => 'two_column',
            'order' => 9,
            'properties' => [
                'label' => 'Paid Eur',
                'label_width' => 60,
                'value_type' => 'dynamic',
                'dynamic_field' => 'payment.amount',
                'font_size' => 9,
                'label_bold' => false,
                'alignment' => 'left',
                'margin_bottom' => 2,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'text',
            'order' => 10,
            'properties' => [
                'content_type' => 'static',
                'static_value' => 'Paid by Girocard',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'left',
                'margin_bottom' => 3,
            ],
        ]);

        // Footer
        $template->lines()->create([
            'section' => 'footer',
            'type' => 'separator',
            'order' => 0,
            'properties' => [
                'style' => 'dashed',
                'width' => 1,
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'thank_you_message',
            'order' => 1,
            'properties' => [
                'message' => 'Thank you for visiting our center',
                'font_size' => 10,
                'font_style' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 3,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'text',
            'order' => 2,
            'properties' => [
                'content_type' => 'static',
                'static_value' => 'Served by:',
                'font_size' => 9,
                'font_weight' => 'normal',
                'alignment' => 'center',
                'margin_bottom' => 1,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'text',
            'order' => 3,
            'properties' => [
                'content_type' => 'static',
                'static_value' => 'Luay',
                'font_size' => 10,
                'font_weight' => 'bold',
                'alignment' => 'center',
                'margin_bottom' => 5,
            ],
        ]);

        // TSE/Fiskaly Info
        $template->lines()->create([
            'section' => 'footer',
            'type' => 'tse_info',
            'order' => 4,
            'properties' => [
                'show_tss_serial' => true,
                'show_transaction_number' => true,
                'show_signature_counter' => true,
                'show_timestamp' => true,
                'font_size' => 7,
                'alignment' => 'center',
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ]);

        // QR Code
        $template->lines()->create([
            'section' => 'footer',
            'type' => 'qr_code',
            'order' => 5,
            'properties' => [
                'size' => 120,
                'alignment' => 'center',
                'error_correction' => 'M',
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ]);
    }

    /**
     * Create compact 58mm template
     */
    protected function createCompact58mmTemplate(): void
    {
        $template = InvoiceTemplate::create([
            'name' => 'Compact Receipt (58mm)',
            'description' => 'Compact receipt for 58mm thermal printers',
            'is_active' => true,
            'is_default' => false,
            'language' => 'en',
            'paper_size' => '58mm',
            'paper_width' => 58,
            'font_family' => 'Arial',
            'font_size' => 9,
            'global_styles' => [
                'primary_color' => '#000000',
                'secondary_color' => '#666666',
                'line_height' => 1.1,
                'padding' => 3,
                'border_color' => '#cccccc',
            ],
            'company_info' => [
                'name' => 'My Shop',
                'address' => "123 Main St",
                'phone' => '+1 234 567 8900',
                'tax_number' => 'TAX123',
                'email' => '',
                'logo_path' => null,
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'text',
            'order' => 0,
            'properties' => [
                'content_type' => 'dynamic',
                'dynamic_field' => 'company.name',
                'font_size' => 11,
                'font_weight' => 'bold',
                'alignment' => 'center',
            ],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'separator',
            'order' => 1,
            'properties' => ['style' => 'solid'],
        ]);

        $template->lines()->create([
            'section' => 'header',
            'type' => 'invoice_number',
            'order' => 2,
            'properties' => ['show_label' => true, 'font_size' => 9],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'items_table',
            'order' => 0,
            'properties' => [
                'show_item_numbers' => false,
                'show_quantity' => true,
                'show_unit_price' => false,
                'show_tax_rate' => false,
                'show_total' => true,
                'table_border' => false,
                'font_size' => 8,
            ],
        ]);

        $template->lines()->create([
            'section' => 'body',
            'type' => 'totals_summary',
            'order' => 1,
            'properties' => [
                'show_subtotal' => false,
                'show_tax_breakdown' => true,
                'show_total' => true,
                'font_size' => 9,
            ],
        ]);

        $template->lines()->create([
            'section' => 'footer',
            'type' => 'thank_you_message',
            'order' => 0,
            'properties' => [
                'message' => 'Thank you!',
                'font_size' => 9,
                'alignment' => 'center',
            ],
        ]);
    }
}
