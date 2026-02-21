<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PrinterSetting;
use App\Models\InvoiceTemplate;
use App\Services\Print\PrintService;
use Illuminate\Http\Request;

class PrintController extends Controller {
    protected PrintService $printService;

    public function __construct(PrintService $printService) {
        $this->printService = $printService;
    }

    /**
     * Print single invoice (Browser)
     * GET /invoice/{invoice}/print
     */
    public function print(Request $request, Invoice $invoice) {
        try {
            // Get printer
            $printerId = $request->get('printer_id');
            $printer = $printerId
                ? PrinterSetting::findOrFail($printerId)
                : PrinterSetting::getDefault();

            // Get template
            $templateId = $request->get('template_id');
            $template = $templateId
                ? InvoiceTemplate::findOrFail($templateId)
                : $invoice->getTemplateOrDefault();

            // Get copies
            $copies = $request->get('copies', 1);
            // Print
            $result = $this->printService->print($invoice, $printer, $template, $copies);

            if (!$result['success']) {
                return response('Print Error: ' . $result['error'], 500);
            }

            return response($result['html'])->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Print single invoice (API)
     * POST /api/invoice/{invoice}/print
     */
    public function apiPrint(Request $request, Invoice $invoice) {
        $validated = $request->validate([
            'printer_id' => 'nullable|exists:printer_settings,id',
            'template_id' => 'nullable|exists:invoice_templates,id',
            'copies' => 'nullable|integer|min:1|max:10',
        ]);

        $printer = isset($validated['printer_id'])
            ? PrinterSetting::find($validated['printer_id'])
            : PrinterSetting::getDefault();

        $template = isset($validated['template_id'])
            ? InvoiceTemplate::find($validated['template_id'])
            : $invoice->getTemplateOrDefault();

        $copies = $validated['copies'] ?? 1;

        $result = $this->printService->print($invoice, $printer, $template, $copies);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Invoice printed successfully',
                'data' => [
                    'print_log_id' => $result['print_log_id'],
                    'print_number' => $result['print_number'],
                    'copy_label' => $result['copy_label'],
                    'printer' => $result['printer'],
                    'print_url' => $this->printService->getPrintUrl($invoice, $printer?->id),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Print failed',
            'error' => $result['error'],
        ], 500);
    }

    /**
     * Batch print (Browser)
     * GET /invoices/print-batch?invoice_ids=1,2,3
     */
    public function printBatch(Request $request) {
        $invoiceIds = explode(',', $request->get('invoice_ids', ''));

        if (empty($invoiceIds)) {
            return response('No invoices specified', 400);
        }

        $printerId = $request->get('printer_id');
        $printer = $printerId ? PrinterSetting::find($printerId) : null;

        $result = $this->printService->printBatch($invoiceIds, $printer);

        if (!$result['success']) {
            return response('Batch print error: ' . $result['error'], 500);
        }

        return response($result['html'])->header('Content-Type', 'text/html');
    }

    /**
     * Batch print (API)
     * POST /api/invoices/print-batch
     */
    public function apiPrintBatch(Request $request) {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'printer_id' => 'nullable|exists:printer_settings,id',
            'template_id' => 'nullable|exists:invoice_templates,id',
        ]);

        $printer = isset($validated['printer_id'])
            ? PrinterSetting::find($validated['printer_id'])
            : PrinterSetting::getDefault();

        $template = isset($validated['template_id'])
            ? InvoiceTemplate::find($validated['template_id'])
            : null;

        $result = $this->printService->printBatch(
            $validated['invoice_ids'],
            $printer,
            $template
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Batch print completed: {$result['successful']}/{$result['total']} successful"
                : 'Batch print failed',
            'data' => $result,
        ]);
    }

    /**
     * Test printer connection
     * POST /api/printer/{printer}/test
     */
    public function testPrinter(PrinterSetting $printer) {
        $result = $this->printService->testPrinter($printer);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Get print statistics
     * GET /api/print/statistics
     */
    public function statistics(Request $request) {
        $printerId = $request->get('printer_id');
        $stats = $this->printService->getStatistics($printerId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent print logs
     * GET /api/print/logs
     */
    public function logs(Request $request) {
        $limit = $request->get('limit', 10);
        $printerId = $request->get('printer_id');

        $logs = $this->printService->getRecentLogs($limit, $printerId);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get print URL
     * GET /api/invoice/{invoice}/print-url
     */
    public function getPrintUrl(Request $request, Invoice $invoice) {
        $printerId = $request->get('printer_id');
        $url = $this->printService->getPrintUrl($invoice, $printerId);

        return response()->json([
            'success' => true,
            'url' => $url,
        ]);
    }
}
