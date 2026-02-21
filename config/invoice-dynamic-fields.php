<?php

return [
// invoice-dynamic-fields.php
    /*
    |--------------------------------------------------------------------------
    | Dynamic Fields
    |--------------------------------------------------------------------------
    |
    | All available dynamic fields that can be used in invoice templates.
    | These fields are automatically populated from invoice data.
    |
    | Structure:
    | 'field.key' => [
    |     'label' => 'Display Label',
    |     'category' => 'Category Name',
    |     'example' => 'Example value for preview',
    | ]
    |
    */

    'fields' => [

        // ============================================================
        // COMPANY INFORMATION
        // ============================================================

        'company.name' => [
            'label' => 'Company Name',
            'category' => 'Company',
            'example' => 'My Salon',
        ],

        'company.address' => [
            'label' => 'Company Address',
            'category' => 'Company',
            'example' => '123 Main Street, Berlin',
        ],

        'company.phone' => [
            'label' => 'Company Phone',
            'category' => 'Company',
            'example' => '+49 30 12345678',
        ],

        'company.email' => [
            'label' => 'Company Email',
            'category' => 'Company',
            'example' => 'info@mysalon.com',
        ],

        'company.tax_number' => [
            'label' => 'Company Tax Number',
            'category' => 'Company',
            'example' => 'DE123456789',
        ],

        // ============================================================
        // INVOICE INFORMATION
        // ============================================================

        'invoice.number' => [
            'label' => 'Invoice Number',
            'category' => 'Invoice',
            'example' => 'INV-2026-001',
        ],

        'invoice.date' => [
            'label' => 'Invoice Date',
            'category' => 'Invoice',
            'example' => '31.01.2026',
        ],

        'invoice.time' => [
            'label' => 'Invoice Time',
            'category' => 'Invoice',
            'example' => '14:30',
        ],

        'invoice.datetime' => [
            'label' => 'Invoice Date & Time',
            'category' => 'Invoice',
            'example' => '31.01.2026 14:30',
        ],

        'invoice.status' => [
            'label' => 'Invoice Status',
            'category' => 'Invoice',
            'example' => 'Paid',
        ],

        'invoice.notes' => [
            'label' => 'Invoice Notes',
            'category' => 'Invoice',
            'example' => 'Thank you for your business',
        ],

        // ============================================================
        // CUSTOMER INFORMATION
        // ============================================================

        'customer.name' => [
            'label' => 'Customer Name',
            'category' => 'Customer',
            'example' => 'John Doe',
        ],

        'customer.email' => [
            'label' => 'Customer Email',
            'category' => 'Customer',
            'example' => 'john.doe@example.com',
        ],

        'customer.phone' => [
            'label' => 'Customer Phone',
            'category' => 'Customer',
            'example' => '+49 176 12345678',
        ],

        'customer.address' => [
            'label' => 'Customer Address',
            'category' => 'Customer',
            'example' => 'Hauptstraße 10, 10115 Berlin',
        ],

        // ============================================================
        // TOTALS
        // ============================================================

        'invoice.subtotal' => [
            'label' => 'Subtotal (Net Amount)',
            'category' => 'Totals',
            'example' => '294.11',
        ],

        'invoice.tax_rate' => [
            'label' => 'Tax Rate (%)',
            'category' => 'Totals',
            'example' => '19',
        ],

        'invoice.tax_amount' => [
            'label' => 'Tax Amount',
            'category' => 'Totals',
            'example' => '55.89',
        ],

        'invoice.total' => [
            'label' => 'Total Amount (Gross)',
            'category' => 'Totals',
            'example' => '350.00',
        ],

        'invoice.paid_amount' => [
            'label' => 'Paid Amount',
            'category' => 'Totals',
            'example' => '350.00',
        ],

        'invoice.remaining' => [
            'label' => 'Remaining Amount',
            'category' => 'Totals',
            'example' => '0.00',
        ],

        // ============================================================
        // PAYMENT INFORMATION
        // ============================================================

        'payment.method' => [
            'label' => 'Payment Method',
            'category' => 'Payment',
            'example' => 'Cash',
        ],

        'payment.reference' => [
            'label' => 'Payment Reference',
            'category' => 'Payment',
            'example' => 'CASH-001',
        ],

        'payment.date' => [
            'label' => 'Payment Date',
            'category' => 'Payment',
            'example' => '31.01.2026',
        ],

        'payment.amount' => [
            'label' => 'Payment Amount',
            'category' => 'Payment',
            'example' => '350.00',
        ],

        // ============================================================
        // EMPLOYEE / STAFF
        // ============================================================

        'employee.name' => [
            'label' => 'Employee Name',
            'category' => 'Staff',
            'example' => 'Maria Schmidt',
        ],

        'employee.signature' => [
            'label' => 'Employee Signature',
            'category' => 'Staff',
            'example' => 'M.S.',
        ],

        // ============================================================
        // TSE / FISKALY
        // ============================================================

        'fiskaly.tss_serial' => [
            'label' => 'TSS Serial Number',
            'category' => 'Fiskaly/TSE',
            'example' => 'd0a4be4774b2d7829cc753ebe740b4b6...',
        ],

        'fiskaly.transaction_number' => [
            'label' => 'Transaction Number',
            'category' => 'Fiskaly/TSE',
            'example' => '42',
        ],

        'fiskaly.signature_counter' => [
            'label' => 'Signature Counter',
            'category' => 'Fiskaly/TSE',
            'example' => '123',
        ],

        'fiskaly.time_start' => [
            'label' => 'Transaction Start Time',
            'category' => 'Fiskaly/TSE',
            'example' => '2026-01-31T14:30:25+00:00',
        ],

        'fiskaly.time_end' => [
            'label' => 'Transaction End Time',
            'category' => 'Fiskaly/TSE',
            'example' => '2026-01-31T14:30:26+00:00',
        ],

        'fiskaly.client_serial' => [
            'label' => 'Client Serial',
            'category' => 'Fiskaly/TSE',
            'example' => 'POS-BERLIN-STORE01-2026',
        ],

        // ============================================================
        // APPOINTMENT (if applicable)
        // ============================================================

        'appointment.date' => [
            'label' => 'Appointment Date',
            'category' => 'Appointment',
            'example' => '31.01.2026',
        ],

        'appointment.time' => [
            'label' => 'Appointment Time',
            'category' => 'Appointment',
            'example' => '14:00',
        ],

        'appointment.duration' => [
            'label' => 'Appointment Duration',
            'category' => 'Appointment',
            'example' => '60 min',
        ],

        // ============================================================
        // SYSTEM
        // ============================================================

        'system.current_date' => [
            'label' => 'Current Date',
            'category' => 'System',
            'example' => '31.01.2026',
        ],

        'system.current_time' => [
            'label' => 'Current Time',
            'category' => 'System',
            'example' => '14:30',
        ],

        'system.current_datetime' => [
            'label' => 'Current Date & Time',
            'category' => 'System',
            'example' => '31.01.2026 14:30',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Categories Order
    |--------------------------------------------------------------------------
    |
    | Define the order in which categories appear in the UI
    |
    */

    'categories_order' => [
        'Company',
        'Invoice',
        'Customer',
        'Totals',
        'Payment',
        'Staff',
        'Appointment',
        'Fiskaly/TSE',
        'System',
    ],

];
