<?php

namespace App\Services\InvoiceTemplate;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;

class DynamicFieldResolver
{
    protected Invoice $invoice;
    protected InvoiceTemplate $template;

    public function __construct(Invoice $invoice, InvoiceTemplate $template)
    {
        $this->invoice = $invoice;
        $this->template = $template;
    }

    /**
     * Resolve a dynamic field value
     */
    public function resolve(string $field): string
    {
        return match(true) {
            // Company fields
            str_starts_with($field, 'company.') => $this->resolveCompanyField($field),

            // Invoice fields
            str_starts_with($field, 'invoice.') => $this->resolveInvoiceField($field),

            // Customer fields
            str_starts_with($field, 'customer.') => $this->resolveCustomerField($field),

            // Payment fields
            str_starts_with($field, 'payment.') => $this->resolvePaymentField($field),

            // Employee fields
            str_starts_with($field, 'employee.') => $this->resolveEmployeeField($field),

            // Fiskaly fields
            str_starts_with($field, 'fiskaly.') => $this->resolveFiskalyField($field),

            // Appointment fields
            str_starts_with($field, 'appointment.') => $this->resolveAppointmentField($field),

            // System fields
            str_starts_with($field, 'system.') => $this->resolveSystemField($field),

            default => $field,
        };
    }

    /**
     * Resolve company field
     */
    protected function resolveCompanyField(string $field): string
    {
        $key = str_replace('company.', '', $field);

        return match($key) {
            'name' => $this->template->getCompanyInfo('name', config('app.name')),
            'address' => $this->template->getCompanyInfo('address', ''),
            'phone' => $this->template->getCompanyInfo('phone', ''),
            'email' => $this->template->getCompanyInfo('email', ''),
            'tax_number' => $this->template->getCompanyInfo('tax_number', ''),
            default => '',
        };
    }

    /**
     * Resolve invoice field
     */
    protected function resolveInvoiceField(string $field): string
    {
        $key = str_replace('invoice.', '', $field);

        return match($key) {
            'number' => $this->invoice->invoice_number ?? 'DRAFT',
            'date' => $this->invoice->created_at?->format('d.m.Y') ?? '',
            'time' => $this->invoice->created_at?->format('H:i') ?? '',
            'datetime' => $this->invoice->created_at?->format('d.m.Y H:i') ?? '',
            'status' => $this->invoice->status?->getLabel() ?? '',
            'notes' => $this->invoice->notes ?? '',
            'subtotal' => number_format($this->invoice->subtotal ?? 0, 2),
            'tax_rate' => number_format($this->invoice->tax_rate ?? 0, 2),
            'tax_amount' => number_format($this->invoice->tax_amount ?? 0, 2),
            'total' => number_format($this->invoice->total_amount ?? 0, 2),
            'paid_amount' => number_format($this->invoice->total_amount ?? 0, 2),
            'remaining' => '0.00',
            default => '',
        };
    }

    /**
     * Resolve customer field
     */
    protected function resolveCustomerField(string $field): string
    {
        $key = str_replace('customer.', '', $field);
        $customer = $this->invoice->customer;

        if (!$customer) {
        return match($key) {
            'name' => $this->invoice->appointment?->customer_name ?? '',
            'email' => $this->invoice->appointment?->customer_email ?? '' ,
            'phone' =>$this->invoice->appointment?->customer_phone ?? '',
            'address' =>  '',
            
            default => '',
        };
        }


        return match($key) {
            'name' => $customer->name ??$this->invoice->getCustomerName() ?? '',
            'email' => $customer->email ?? '',
            'phone' => $customer->phone ?? '',
            'address' => $customer->address ?? '',
            default => '',
        };
    }

    /**
     * Resolve payment field
     */
    protected function resolvePaymentField(string $field): string
    {
        $key = str_replace('payment.', '', $field);
        $payment = $this->invoice->payment;

        if (!$payment) {
            return '';
        }

        return match($key) {
            'method' => $payment->method ?? 'Cash',
            'reference' => $payment->reference ?? '',
            'date' => $payment->created_at?->format('d.m.Y') ?? '',
            'amount' => number_format($payment->amount ?? 0, 2),
            default => '',
        };
    }

    /**
     * Resolve employee field
     */
    protected function resolveEmployeeField(string $field): string
    {
        $key = str_replace('employee.', '', $field);
        $appointment = $this->invoice->appointment;

        if (!$appointment || !$appointment->employee) {
            return '';
        }

        $employee = $appointment->employee;

        return match($key) {
            'name' => $employee->name ?? '',
            'signature' => $this->getEmployeeSignature($employee->name ?? ''),
            default => '',
        };
    }

    /**
     * Resolve Fiskaly field
     */
    protected function resolveFiskalyField(string $field): string
    {
        $key = str_replace('fiskaly.', '', $field);
        $fiskalyData = $this->invoice->invoice_data ?? [];

        return match($key) {
            'tss_serial' => $fiskalyData['fiskaly_tss_serial'] ?? '',
            'transaction_number' => (string)($fiskalyData['fiskaly_transaction_number'] ?? ''),
            'signature_counter' => (string)($fiskalyData['fiskaly_signature']['counter'] ?? ''),
            'time_start' => isset($fiskalyData['fiskaly_time_start'])
                ? date('Y-m-d H:i:s', $fiskalyData['fiskaly_time_start'])
                : '',
            'time_end' => isset($fiskalyData['fiskaly_time_end'])
                ? date('Y-m-d H:i:s', $fiskalyData['fiskaly_time_end'])
                : '',
            'client_serial' => $fiskalyData['fiskaly_client_serial'] ?? '',
            default => '',
        };
    }

    /**
     * Resolve appointment field
     */
    protected function resolveAppointmentField(string $field): string
    {
        $key = str_replace('appointment.', '', $field);
        $appointment = $this->invoice->appointment;

        if (!$appointment) {
            return '';
        }

        return match($key) {
            'date' => $appointment->scheduled_at?->format('d.m.Y') ?? '',
            'time' => $appointment->scheduled_at?->format('H:i') ?? '',
            'duration' => ($appointment->duration ?? 0) . ' min',
            default => '',
        };
    }

    /**
     * Resolve system field
     */
    protected function resolveSystemField(string $field): string
    {
        $key = str_replace('system.', '', $field);

        return match($key) {
            'current_date' => now()->format('d.m.Y'),
            'current_time' => now()->format('H:i'),
            'current_datetime' => now()->format('d.m.Y H:i'),
            default => '',
        };
    }

    /**
     * Get employee signature (initials)
     */
    protected function getEmployeeSignature(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = '';

        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(mb_substr($part, 0, 1));
            }
        }

        return $initials;
    }

    /**
     * Get all available fields
     */
    public static function getAllFields(): array
    {
        return config('invoice-dynamic-fields.fields', []);
    }

    /**
     * Get fields grouped by category
     */
    public static function getFieldsByCategory(): array
    {
        $fields = self::getAllFields();
        $categoriesOrder = config('invoice-dynamic-fields.categories_order', []);
        $grouped = [];

        foreach ($fields as $key => $field) {
            $category = $field['category'] ?? 'Other';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$key] = $field['label'];
        }

        // Sort by categories order
        $sorted = [];
        foreach ($categoriesOrder as $category) {
            if (isset($grouped[$category])) {
                $sorted[$category] = $grouped[$category];
            }
        }

        // Add remaining categories
        foreach ($grouped as $category => $items) {
            if (!isset($sorted[$category])) {
                $sorted[$category] = $items;
            }
        }

        return $sorted;
    }

    /**
     * Get example value for a field
     */
    public static function getExampleValue(string $field): string
    {
        $allFields = self::getAllFields();
        return $allFields[$field]['example'] ?? $field;
    }
}
