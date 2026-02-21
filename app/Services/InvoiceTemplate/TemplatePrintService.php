<?php

namespace App\Services\InvoiceTemplate;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;

class TemplatePrintService
{
    protected TemplateBuilderService $builder;

    public function __construct()
    {
        $this->builder = new TemplateBuilderService();
    }

    /**
     * Print invoice to POS printer
     */
    public function print(Invoice $invoice, ?InvoiceTemplate $template = null): string
    {
        $html = $this->builder->build($invoice, $template);

        // Add auto-print script
        $html = str_replace(
            '</body>',
            '<script>window.onload = function() { window.print(); };</script></body>',
            $html
        );

        return $html;
    }

    /**
     * Generate print URL for an invoice
     */
    public function getPrintUrl(Invoice $invoice, ?int $templateId = null): string
    {
        $params = ['invoice' => $invoice->id];

        if ($templateId) {
            $params['template_id'] = $templateId;
        }

        return route('invoice.print', $params);
    }

    /**
     * Silent print (returns HTML that auto-prints)
     */
    public function silentPrint(Invoice $invoice, ?InvoiceTemplate $template = null): string
    {
        return $this->print($invoice, $template);
    }

    /**
     * Batch print multiple invoices
     */
    public function printBatch(array $invoiceIds, ?InvoiceTemplate $template = null): string
    {
        $invoices = Invoice::with(['customer', 'items', 'payment', 'appointment'])
            ->whereIn('id', $invoiceIds)
            ->get();

        $htmlPages = [];

        foreach ($invoices as $invoice) {
            $htmlPages[] = $this->builder->build($invoice, $template);
        }

        // Combine with page breaks
        $combinedHtml = implode('<div style="page-break-after: always;"></div>', $htmlPages);

        // Add auto-print script
        $combinedHtml .= '<script>window.onload = function() { window.print(); };</script>';

        return $combinedHtml;
    }

    /**
     * Open cash drawer command (ESC/POS)
     */
    public function openCashDrawer(): string
    {
        // ESC/POS command to open cash drawer
        // This would need to be sent to the printer
        return chr(27) . chr(112) . chr(0) . chr(25) . chr(250);
    }

    /**
     * Get print settings
     */
    public function getPrintSettings(InvoiceTemplate $template): array
    {
        return [
            'paper_width' => $template->paper_width . 'mm',
            'orientation' => 'portrait',
            'margins' => '0mm',
            'scale' => 1,
        ];
    }

    /**
     * Print with ESC/POS commands (advanced)
     * Requires mike42/escpos-php package
     */
    public function printWithEscPos(Invoice $invoice, string $printerPath = '/dev/usb/lp0'): bool
    {
        // This is a placeholder for ESC/POS printing
        // Install: composer require mike42/escpos-php

        /*
        try {
            $connector = new \Mike42\Escpos\PrintConnectors\FilePrintConnector($printerPath);
            $printer = new \Mike42\Escpos\Printer($connector);

            // Print header
            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($invoice->template->getCompanyInfo('name') . "\n");
            $printer->setEmphasis(false);

            // Print invoice details
            $printer->text("Invoice: " . $invoice->invoice_number . "\n");
            $printer->text("Date: " . $invoice->created_at->format('d.m.Y H:i') . "\n");
            $printer->text(str_repeat('-', 32) . "\n");

            // Print items
            foreach ($invoice->items as $item) {
                $printer->text(sprintf(
                    "%s x%d\n%.2f\n",
                    $item->description,
                    $item->quantity,
                    $item->total_amount
                ));
            }

            $printer->text(str_repeat('-', 32) . "\n");

            // Print total
            $printer->setEmphasis(true);
            $printer->text(sprintf("TOTAL: %.2f\n", $invoice->total_amount));
            $printer->setEmphasis(false);

            // Cut paper
            $printer->cut();
            $printer->close();

            return true;
        } catch (\Exception $e) {
            \Log::error('ESC/POS print failed: ' . $e->getMessage());
            return false;
        }
        */

        return false; // Not implemented without mike42/escpos-php
    }
}
