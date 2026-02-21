<?php

namespace App\Services\InvoiceTemplate;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\TemplateLine;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TemplateBuilderService
{
    protected Invoice $invoice;
    protected InvoiceTemplate $template;
    protected DynamicFieldResolver $fieldResolver;
    protected bool $isPreview = false;

    /**
     * Build invoice HTML from template
     */
    public function build(Invoice $invoice, ?InvoiceTemplate $template = null): string
    {
        $this->invoice = $invoice;
        $this->template = $template ?? $invoice->getTemplateOrDefault();

        if (!$this->template) {
            throw new \Exception('No invoice template available');
        }

        // Load necessary relationships
        $this->loadInvoiceRelationships();

        // Initialize field resolver
        $this->fieldResolver = new DynamicFieldResolver($this->invoice, $this->template);

        // Generate the HTML
        return $this->generateHtml();
    }

    /**
     * Build preview with sample data
     */
    public function buildPreview(InvoiceTemplate $template): string
    {
        $this->isPreview = true;
        $this->template = $template;
        $this->invoice = $this->createSampleInvoice();

        // Initialize field resolver
        $this->fieldResolver = new DynamicFieldResolver($this->invoice, $this->template);

        return $this->generateHtml();
    }

    /**
     * Generate the complete HTML
     */
    protected function generateHtml(): string
    {
        $data = [
            'invoice' => $this->invoice,
            'template' => $this->template,
            'builder' => $this,
            'isPreview' => $this->isPreview,
            'styles' => $this->generateStyles(),
        ];

        return View::make('invoices.template-builder', $data)->render();
    }

    /**
     * Render a line
     */
    public function renderLine(TemplateLine $line): string
    {
        if (!$line->is_enabled) {
            return '';
        }
        $bladeView = $line->getBladeView();
        $data = [
            'line' => $line,
            'invoice' => $this->invoice,
            'template' => $this->template,
            'properties' => $line->getMergedProperties(),
            'builder' => $this,
        ];

        try {

            return View::make($bladeView, $data)->render();
        } catch (\Exception $e) {
            Log::error("Error rendering line type {$line->type}: " . $e->getMessage());
            return "<!-- Error rendering {$line->type} -->";
        }
    }

    /**
     * Resolve dynamic field value
     */
    public function resolveDynamicField(string $field): string
    {
        if ($this->isPreview) {
            return DynamicFieldResolver::getExampleValue($field);
        }

        return $this->fieldResolver->resolve($field);
    }

    /**
     * Generate CSS styles for the template
     */
    protected function generateStyles(): string
    {
        $paperWidth = $this->template->paper_width;
        $fontFamily = $this->template->font_family;
        $fontSize = $this->template->font_size;

        $globalStyles = $this->template->global_styles ?? [];
        $primaryColor = $globalStyles['primary_color'] ?? '#000000';
        $secondaryColor = $globalStyles['secondary_color'] ?? '#666666';
        $lineHeight = $globalStyles['line_height'] ?? 1.2;
        $padding = $globalStyles['padding'] ?? 5;
        $borderColor = $globalStyles['border_color'] ?? '#cccccc';

        return "
        <style>
            @media print {
                @page {
                    size: {$paperWidth}mm auto;
                    margin: 0;
                }
                body {
                    margin: 0;
                    padding: 0;
                }
                .no-print {
                    display: none !important;
                }
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: {$fontFamily}, sans-serif;
                font-size: {$fontSize}px;
                line-height: {$lineHeight};
                color: {$primaryColor};
                width: {$paperWidth}mm;
                margin: 0 auto;
                padding: {$padding}px;
                background: white;
            }

            .invoice-container {
                width: 100%;
            }

            .invoice-section {
                margin-bottom: 10px;
            }

            .line-item {
                margin: 0;
            }

            /* Text Utilities */
            .text-left { text-align: left; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }

            .font-bold { font-weight: bold; }
            .font-normal { font-weight: normal; }
            .font-italic { font-style: italic; }

            /* Separator */
            .separator-line {
                width: 100%;
                border: 0;
                margin: 0;
            }

            .separator-solid { border-top-style: solid; }
            .separator-dashed { border-top-style: dashed; }
            .separator-dotted { border-top-style: dotted; }

            /* Items Table */
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin: 5px 0;
            }

            .items-table th {
                padding: 4px 2px;
                text-align: left;
                font-size: 0.9em;
                font-weight: bold;
            }

            .items-table td {
                padding: 3px 2px;
                font-size: 0.9em;
            }

            .items-table.bordered {
                border: 1px solid {$borderColor};
            }

            .items-table.bordered th,
            .items-table.bordered td {
                border: 1px solid {$borderColor};
            }

            .items-table.row-separator tr:not(:last-child) {
                border-bottom: 1px solid {$borderColor};
            }

            /* Totals */
            .totals-row {
                display: flex;
                justify-content: space-between;
                padding: 2px 0;
                font-size: 0.95em;
            }

            .totals-row.highlight {
                font-weight: bold;
                font-size: 1.1em;
                border-top: 2px solid {$primaryColor};
                padding-top: 5px;
                margin-top: 5px;
            }

            /* Two Column Layout */
            .two-column {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            /* Customer Info Block */
            .customer-info-block {
                padding: 5px;
                background: #f9f9f9;
                border-radius: 3px;
                font-size: 0.9em;
            }

            /* QR Code */
            .qr-code-container {
                text-align: center;
            }

            .qr-code-container img {
                max-width: 100%;
                height: auto;
            }

            /* TSE Info */
            .tse-info {
                font-size: 0.7em;
                color: {$secondaryColor};
                line-height: 1.3;
            }

            /* Payment Info */
            .payment-info-block {
                padding: 5px;
                background: #f5f5f5;
                border-radius: 3px;
                text-align: center;
            }

            /* Utilities */
            .mb-1 { margin-bottom: 2px; }
            .mb-2 { margin-bottom: 5px; }
            .mb-3 { margin-bottom: 10px; }

            .mt-1 { margin-top: 2px; }
            .mt-2 { margin-top: 5px; }
            .mt-3 { margin-top: 10px; }
        </style>
        ";
    }

    /**
     * Generate QR code for invoice
     */
    public function generateQrCode(array $properties): string
    {
        try {
            // Get QR data from Fiskaly if available
            $qrData = $this->invoice->invoice_data['fiskaly_qr_code'] ?? "aHR0cHM6Ly9jaGF0Z3B0LmNvbS8=";

            if (!$qrData) {
                // Generate basic QR code with invoice info
                $qrData = $this->buildBasicQrData();
            }

            $size = $properties['size'] ?? 150;
            $errorCorrection = $properties['error_correction'] ?? 'M';

            // Generate QR code as base64 image
            $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size($size)
                ->margin(1)
                ->errorCorrection($errorCorrection)
                ->generate($qrData);
            return base64_encode($qrCode);
        } catch (\Exception $e) {
            Log::error('QR Code generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Build basic QR code data
     */
    protected function buildBasicQrData(): string
    {
        return implode('|', [
            'Invoice: ' . ($this->invoice->invoice_number ?? 'DRAFT'),
            'Date: ' . $this->invoice->created_at?->format('Y-m-d H:i'),
            'Total: ' . number_format($this->invoice->total_amount ?? 0, 2),
            'Tax: ' . number_format($this->invoice->tax_amount ?? 0, 2),
            'Customer: ' . ($this->invoice->customer?->name ?? 'Guest'),
        ]);
    }

    /**
     * Load necessary invoice relationships
     */
    protected function loadInvoiceRelationships(): void
    {
        $this->invoice->load([
            'customer',
            'items.itemable',
            'payment',
            'appointment.customer',
        ]);
    }

    /**
     * Create sample invoice for preview
     */
    public function createSampleInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->invoice_number = 'INV-2026-PREVIEW';
        $invoice->created_at = now();
        $invoice->subtotal = 294.11;
        $invoice->tax_rate = 19.00;
        $invoice->tax_amount = 55.89;
        $invoice->total_amount = 350.00;
        $invoice->status = \App\Enum\InvoiceStatus::PAID;
        $invoice->notes = 'Sample invoice for preview';

        // Sample customer
        $customer = new \App\Models\User();
        $customer->name = 'John Doe';
        $customer->email = 'john.doe@example.com';
        $customer->phone = '+49 176 12345678';
        $invoice->setRelation('customer', $customer);

        // Sample items
        $items = collect([
            $this->createSampleItem('Men\'s Haircut', 1, 25.00),
            $this->createSampleItem('Beard Trim', 1, 15.00),
            $this->createSampleItem('Hair Product', 2, 12.50),
        ]);
        $invoice->setRelation('items', $items);

        // Sample payment
        $payment = new \App\Models\Payment();
        $payment->method = 'Cash';
        $payment->amount = 350.00;
        $payment->reference = 'CASH-PREVIEW';
        $payment->created_at = now();
        $invoice->setRelation('payment', $payment);

        // Sample Fiskaly data
        $invoice->invoice_data = [
            'fiskaly_transaction_number' => 42,
            'fiskaly_tss_serial' => 'd0a4be4774b2d7829cc753ebe740b4b640538f90d841d054c91ff0b63bb19cf5',
            'fiskaly_signature' => ['counter' => 123],
            'fiskaly_time_start' => time(),
            'fiskaly_time_end' => time() + 1,
            'fiskaly_client_serial' => 'POS-PREVIEW-2026',
        ];

        return $invoice;
    }

    /**
     * Create sample invoice item
     */
    protected function createSampleItem(string $description, int $quantity, float $unitPrice): object
    {
        $item = new \App\Models\InvoiceItem();
        $item->description = $description;
        $item->quantity = $quantity;
        $item->unit_price = $unitPrice;
        $item->tax_rate = 19.00;

        $subtotal = $quantity * $unitPrice;
        $taxAmount = $subtotal * 0.19;

        $item->tax_amount = $taxAmount;
        $item->total_amount = $subtotal + $taxAmount;

        return $item;
    }
}
