<?php

namespace App\Services\InvoiceTemplate;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;

class PdfGeneratorService
{
    protected TemplateBuilderService $builder;

    public function __construct()
    {
        $this->builder = new TemplateBuilderService();
    }

    /**
     * Generate PDF from invoice
     * Requires: composer require barryvdh/laravel-dompdf
     */
    public function generatePdf(Invoice $invoice, ?InvoiceTemplate $template = null): mixed
    {
        $html = $this->builder->build($invoice, $template);

        // Check if dompdf is available
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new \Exception('PDF library not installed. Run: composer require barryvdh/laravel-dompdf');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

        $template = $template ?? $invoice->getTemplateOrDefault();

        // Set paper size
        $pdf->setPaper([0, 0, $this->mmToPoints($template->paper_width), 841.89], 'portrait');

        return $pdf;
    }

    /**
     * Download PDF
     */
    public function downloadPdf(Invoice $invoice, ?InvoiceTemplate $template = null, ?string $filename = null): mixed
    {
        $pdf = $this->generatePdf($invoice, $template);

        $filename = $filename ?? 'invoice-' . ($invoice->invoice_number ?? $invoice->id) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Stream PDF (display in browser)
     */
    public function streamPdf(Invoice $invoice, ?InvoiceTemplate $template = null): mixed
    {
        $pdf = $this->generatePdf($invoice, $template);

        return $pdf->stream();
    }

    /**
     * Save PDF to storage
     */
    public function savePdf(Invoice $invoice, ?InvoiceTemplate $template = null, ?string $path = null): string
    {
        $pdf = $this->generatePdf($invoice, $template);

        $path = $path ?? 'invoices/' . ($invoice->invoice_number ?? $invoice->id) . '.pdf';

        \Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Get PDF as base64 string
     */
    public function getPdfBase64(Invoice $invoice, ?InvoiceTemplate $template = null): string
    {
        $pdf = $this->generatePdf($invoice, $template);

        return base64_encode($pdf->output());
    }

    /**
     * Email PDF as attachment
     */
    public function emailPdf(Invoice $invoice, string $email, ?InvoiceTemplate $template = null): bool
    {
        try {
            $pdf = $this->generatePdf($invoice, $template);
            $filename = 'invoice-' . ($invoice->invoice_number ?? $invoice->id) . '.pdf';

            \Mail::send('emails.invoice', ['invoice' => $invoice], function ($message) use ($email, $pdf, $filename, $invoice) {
                $message->to($email)
                    ->subject('Invoice ' . ($invoice->invoice_number ?? $invoice->id))
                    ->attachData($pdf->output(), $filename, [
                        'mime' => 'application/pdf',
                    ]);
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to email PDF: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate PDF for multiple invoices
     */
    public function generateBatchPdf(array $invoiceIds, ?InvoiceTemplate $template = null): mixed
    {
        $invoices = Invoice::with(['customer', 'items', 'payment', 'appointment'])
            ->whereIn('id', $invoiceIds)
            ->get();

        $htmlPages = [];

        foreach ($invoices as $invoice) {
            $htmlPages[] = $this->builder->build($invoice, $template);
        }

        $combinedHtml = implode('<div style="page-break-after: always;"></div>', $htmlPages);

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new \Exception('PDF library not installed. Run: composer require barryvdh/laravel-dompdf');
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($combinedHtml);
    }

    /**
     * Convert millimeters to points (for PDF)
     */
    protected function mmToPoints(float $mm): float
    {
        return $mm * 2.83465;
    }

    /**
     * Get PDF metadata
     */
    public function getPdfMetadata(Invoice $invoice): array
    {
        return [
            'title' => 'Invoice ' . ($invoice->invoice_number ?? $invoice->id),
            'author' => config('app.name'),
            'subject' => 'Invoice',
            'keywords' => 'invoice, receipt, payment',
            'creator' => config('app.name'),
        ];
    }
}
