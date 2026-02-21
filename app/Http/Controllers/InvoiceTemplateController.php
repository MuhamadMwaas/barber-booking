<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceTemplate\TemplateBuilderService;
use App\Services\InvoiceTemplate\TemplatePrintService;
use App\Services\InvoiceTemplate\PdfGeneratorService;
use Illuminate\Http\Request;

class InvoiceTemplateController extends Controller
{
    protected TemplateBuilderService $builder;
    protected TemplatePrintService $printService;
    protected PdfGeneratorService $pdfService;

    public function __construct(
        TemplateBuilderService $builder,
        TemplatePrintService $printService,
        PdfGeneratorService $pdfService
    ) {
        $this->builder = $builder;
        $this->printService = $printService;
        $this->pdfService = $pdfService;
    }

    /**
     * Preview template with sample data
     */
    public function preview(InvoiceTemplate $template)
    {
        try {
            $html = $this->builder->buildPreview($template);
            return response($html)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response('Error generating preview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Print invoice
     */
    public function print(Request $request, Invoice $invoice)
    {
        try {
            // Get template from request or use invoice's template or default
            $templateId = $request->get('template_id');
            $template = $templateId
                ? InvoiceTemplate::findOrFail($templateId)
                : $invoice->getTemplateOrDefault();

            if (!$template) {
                return response('No template available', 404);
            }

            // Generate HTML for printing
            $html = $this->printService->print($invoice, $template);

            return response($html)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response('Error generating invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Print multiple invoices
     */
    public function printBatch(Request $request)
    {
        try {
            $invoiceIds = explode(',', $request->get('invoice_ids', ''));

            if (empty($invoiceIds)) {
                return response('No invoices specified', 400);
            }

            $templateId = $request->get('template_id');
            $template = $templateId ? InvoiceTemplate::find($templateId) : null;

            $html = $this->printService->printBatch($invoiceIds, $template);

            return response($html)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response('Error generating batch: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download invoice as PDF
     */
    public function downloadPdf(Request $request, Invoice $invoice)
    {
        try {
            $templateId = $request->get('template_id');
            $template = $templateId
                ? InvoiceTemplate::findOrFail($templateId)
                : $invoice->getTemplateOrDefault();

            return $this->pdfService->downloadPdf($invoice, $template);
        } catch (\Exception $e) {
            return response('Error generating PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * View invoice as PDF in browser
     */
    public function viewPdf(Request $request, Invoice $invoice)
    {
        try {
            $templateId = $request->get('template_id');
            $template = $templateId
                ? InvoiceTemplate::findOrFail($templateId)
                : $invoice->getTemplateOrDefault();

            return $this->pdfService->streamPdf($invoice, $template);
        } catch (\Exception $e) {
            return response('Error generating PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download batch of invoices as PDF
     */
    public function downloadBatchPdf(Request $request)
    {
        try {
            $invoiceIds = explode(',', $request->get('invoice_ids', ''));

            if (empty($invoiceIds)) {
                return response('No invoices specified', 400);
            }

            $templateId = $request->get('template_id');
            $template = $templateId ? InvoiceTemplate::find($templateId) : null;

            $pdf = $this->pdfService->generateBatchPdf($invoiceIds, $template);

            return $pdf->download('invoices-batch-' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            return response('Error generating PDF batch: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get template as JSON (for API)
     */
    public function show(InvoiceTemplate $template)
    {
        return response()->json([
            'template' => $template,
            'lines' => $template->lines()->ordered()->get(),
        ]);
    }

    /**
     * List available templates (for API)
     */
    public function index(Request $request)
    {
        $query = InvoiceTemplate::query();

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('language')) {
            $query->where('language', $request->get('language'));
        }

        $templates = $query->with('lines')->get();

        return response()->json([
            'templates' => $templates,
        ]);
    }
}
