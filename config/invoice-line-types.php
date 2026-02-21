<?php

return [
// invoice-line-types.php
    /*
    |--------------------------------------------------------------------------
    | Available Line Types
    |--------------------------------------------------------------------------
    |
    | Define all available line types that can be used in invoice templates.
    | Each line type has:
    | - label: Display name in Filament
    | - icon: Heroicon for visual representation
    | - blade_view: Blade partial path for rendering
    | - sections: Where this line can be used (header, body, footer)
    | - unique: Can only appear once in the template
    | - properties: Form fields configuration for Filament
    |
    */

    'types' => [

        // ============================================================
        // BASIC TYPES
        // ============================================================

        'text' => [
            'label' => 'Text Line',
            'icon' => 'heroicon-o-document-text',
            'blade_view' => 'invoices.line-types.text',
            'sections' => ['header', 'footer','body'],
            'unique' => false,
            'properties' => [
                'content_type' => 'dynamic', // static, dynamic
                'static_value' => '',
                'dynamic_field' => null,
                'prefix' => '',
                'suffix' => '',
                'font_size' => 10,
                'font_weight' => 'normal', // normal, bold
                'font_style' => 'normal', // normal, italic
                'alignment' => 'left', // left, center, right
                'color' => '#000000',
                'margin_top' => 0,
                'margin_bottom' => 2,
            ],
        ],

        'separator' => [
            'label' => 'Separator Line',
            'icon' => 'heroicon-o-minus',
            'blade_view' => 'invoices.line-types.separator',
            'sections' => ['header', 'footer','body'],
            'unique' => false,
            'properties' => [
                'style' => 'solid', // solid, dashed, dotted
                'width' => 1,
                'color' => '#000000',
                'margin_top' => 3,
                'margin_bottom' => 3,
            ],
        ],

        'spacer' => [
            'label' => 'Empty Space',
            'icon' => 'heroicon-o-arrows-up-down',
            'blade_view' => 'invoices.line-types.spacer',
            'sections' => ['header', 'footer','body'],
            'unique' => false,
            'properties' => [
                'height' => 10, // in pixels
            ],
        ],

        'image' => [
            'label' => 'Image/Logo',
            'icon' => 'heroicon-o-photo',
            'blade_view' => 'invoices.line-types.image',
            'sections' => ['header', 'footer'],
            'unique' => false,
            'properties' => [
                'image_path' => null,
                'width' => 80,
                'height' => 80,
                'alignment' => 'center',
                'margin_top' => 0,
                'margin_bottom' => 5,
            ],
        ],

        'two_column' => [
            'label' => 'Two Column (Label: Value)',
            'icon' => 'heroicon-o-table-cells',
            'blade_view' => 'invoices.line-types.two-column',
            'sections' => ['header', 'footer','body'],
            'unique' => false,
            'properties' => [
                'label' => 'Label:',
                'label_width' => 50, // percentage
                'value_type' => 'dynamic', // static, dynamic
                'static_value' => '',
                'dynamic_field' => null,
                'font_size' => 10,
                'label_bold' => true,
                'alignment' => 'left',
                'margin_top' => 0,
                'margin_bottom' => 2,
            ],
        ],

        // ============================================================
        // INVOICE SPECIFIC TYPES
        // ============================================================

        'invoice_number' => [
            'label' => 'Invoice Number',
            'icon' => 'heroicon-o-hashtag',
            'blade_view' => 'invoices.line-types.invoice-number',
            'sections' => ['header','body'],
            'unique' => true,
            'properties' => [
                'label' => 'Invoice No:',
                'show_label' => true,
                'font_size' => 10,
                'font_weight' => 'normal',
                'alignment' => 'left',
                'margin_top' => 0,
                'margin_bottom' => 2,
            ],
        ],

        'invoice_date' => [
            'label' => 'Invoice Date & Time',
            'icon' => 'heroicon-o-calendar',
            'blade_view' => 'invoices.line-types.invoice-date',
            'sections' => ['header', 'footer','body'],
            'unique' => false,
            'properties' => [
                'label' => 'Date:',
                'show_label' => true,
                'show_time' => true,
                'format' => 'd.m.Y H:i', // PHP date format
                'font_size' => 10,
                'alignment' => 'left',
                'margin_top' => 0,
                'margin_bottom' => 2,
            ],
        ],

        'customer_info' => [
            'label' => 'Customer Information Block',
            'icon' => 'heroicon-o-user',
            'blade_view' => 'invoices.line-types.customer-info',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true,
            'properties' => [
                'show_name' => true,
                'show_email' => true,
                'show_phone' => true,
                'show_address' => false,
                'font_size' => 9,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ],

        'items_table' => [
            'label' => 'Items Table (Products/Services)',
            'icon' => 'heroicon-o-table-cells',
            'blade_view' => 'invoices.line-types.items-table',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true, // Only one items table per template
            'properties' => [
                'show_item_numbers' => true,
                'show_quantity' => true,
                'show_unit_price' => true,
                'show_tax_rate' => true,
                'show_tax_amount' => false,
                'show_total' => true,
                'table_border' => true,
                'header_background' => '#000000',
                'header_text_color' => '#ffffff',
                'row_separator' => true,
                'font_size' => 9,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ],

        'totals_summary' => [
            'label' => 'Totals Summary (Subtotal, Tax, Total)',
            'icon' => 'heroicon-o-calculator',
            'blade_view' => 'invoices.line-types.totals-summary',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true,
            'properties' => [
                'show_subtotal' => true,
                'show_tax_breakdown' => true,
                'show_total' => true,
                'highlight_total' => true,
                'font_size' => 10,
                'total_font_size' => 12,
                'alignment' => 'right',
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ],

        'payment_info' => [
            'label' => 'Payment Method Info',
            'icon' => 'heroicon-o-credit-card',
            'blade_view' => 'invoices.line-types.payment-info',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true,
            'properties' => [
                'show_method' => true,
                'show_amount' => true,
                'show_reference' => false,
                'font_size' => 10,
                'alignment' => 'center',
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ],

        // ============================================================
        // QR & BARCODE
        // ============================================================

        'qr_code' => [
            'label' => 'QR Code',
            'icon' => 'heroicon-o-qr-code',
            'blade_view' => 'invoices.line-types.qr-code',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true,
            'properties' => [
                'size' => 150,
                'alignment' => 'center',
                'margin_top' => 10,
                'margin_bottom' => 5,
                'error_correction' => 'M', // L, M, Q, H
            ],
        ],

        'barcode' => [
            'label' => 'Barcode',
            'icon' => 'heroicon-o-bars-3-bottom-left',
            'blade_view' => 'invoices.line-types.barcode',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true,
            'properties' => [
                'type' => 'code128', // code128, code39, ean13
                'height' => 50,
                'alignment' => 'center',
                'show_text' => true,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ],
        ],

        // ============================================================
        // TSE / FISKALY
        // ============================================================

        'tse_info' => [
            'label' => 'TSE/Fiskaly Information',
            'icon' => 'heroicon-o-shield-check',
            'blade_view' => 'invoices.line-types.tse-info',
            'sections' => ['header', 'footer', 'body'],
            'unique' => true,
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
        ],

        // ============================================================
        // CUSTOM MESSAGES
        // ============================================================

        'thank_you_message' => [
            'label' => 'Thank You Message',
            'icon' => 'heroicon-o-heart',
            'blade_view' => 'invoices.line-types.thank-you-message',
            'sections' => ['header', 'footer', 'body'],
            'unique' => false,
            'properties' => [
                'message' => 'Thank you for your business!',
                'font_size' => 10,
                'font_style' => 'italic',
                'alignment' => 'center',
                'margin_top' => 10,
                'margin_bottom' => 5,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Line Properties
    |--------------------------------------------------------------------------
    |
    | Default values used when creating new lines
    |
    */

    'defaults' => [
        'font_size' => 10,
        'font_weight' => 'normal',
        'font_style' => 'normal',
        'alignment' => 'left',
        'color' => '#000000',
        'margin_top' => 0,
        'margin_bottom' => 2,
    ],

];
