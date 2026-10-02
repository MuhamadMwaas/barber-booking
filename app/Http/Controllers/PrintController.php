<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PrinterSetting;
use App\Models\InvoiceTemplate;
use App\Services\Print\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Staff printing. Every method that takes an invoice id asks InvoicePolicy
 * (`print`) BEFORE any work — see AUTHZ-03. Customers never print; they read
 * their own receipts through Api\MyInvoiceController.
 */
class PrintController extends Controller {
    /** Upper bound for one batch, so one request cannot dump the whole salon. */
    private const MAX_BATCH = 50;

    protected PrintService $printService;

    public function __construct(PrintService $printService) {
        $this->printService = $printService;
    }

    /**
     * Print single invoice (Browser)
     * GET /invoice/{invoice}/print
     */
    public function print(Request $request, Invoice $invoice) {
        Gate::authorize('print', $invoice);

        $validated = $request->validate([
            'printer_id' => 'nullable|integer|exists:printer_settings,id',
            'template_id' => 'nullable|integer|exists:invoice_templates,id',
            'copies' => 'nullable|integer|min:1|max:10',
        ]);

        $printer = isset($validated['printer_id'])
            ? PrinterSetting::find($validated['printer_id'])
            : PrinterSetting::getDefault();

        $template = isset($validated['template_id'])
            ? InvoiceTemplate::find($validated['template_id'])
            : $invoice->getTemplateOrDefault();

        try {
            $result = $this->printService->print($invoice, $printer, $template, $validated['copies'] ?? 1);
        } catch (\Throwable $e) {
            report($e);
            $result = ['success' => false];
        }

        if (!$result['success']) {
            // PrintService has already logged the reason; the browser gets no
            // internals (the old handler echoed getMessage() to the page).
            return response(__('Unable to print the invoice.'), 500);
        }

        return response($result['html'])->header('Content-Type', 'text/html');
    }

    /**
     * Print single invoice (API)
     * POST /api/invoice/{invoice}/print
     */
    public function apiPrint(Request $request, Invoice $invoice) {
        Gate::authorize('print', $invoice);

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
        ], 500);
    }

    /**
     * Batch print (Browser)
     * GET /invoices/print-batch?invoice_ids=1,2,3
     */
    public function printBatch(Request $request) {
        // explode(',', '') is [''] — a NON-empty array — so the old empty() guard
        // never fired. Parse and validate like the API does instead.
        $ids = array_values(array_filter(
            explode(',', (string) $request->query('invoice_ids', '')),
            fn ($id) => $id !== ''
        ));

        $validated = Validator::make(
            ['invoice_ids' => $ids, 'printer_id' => $request->query('printer_id')],
            $this->batchRules() + ['printer_id' => 'nullable|integer|exists:printer_settings,id'],
        )->validate();

        $invoices = $this->authorizedBatch($validated['invoice_ids']);

        $printer = isset($validated['printer_id']) ? PrinterSetting::find($validated['printer_id']) : null;

        $result = $this->printService->printBatch($invoices->modelKeys(), $printer);

        if (!$result['success']) {
            return response(__('Unable to print the invoices.'), 500);
        }

        return response($result['html'])->header('Content-Type', 'text/html');
    }

    /**
     * Batch print (API)
     * POST /api/invoices/print-batch
     */
    public function apiPrintBatch(Request $request) {
        $validated = $request->validate($this->batchRules() + [
            'printer_id' => 'nullable|exists:printer_settings,id',
            'template_id' => 'nullable|exists:invoice_templates,id',
        ]);

        $invoices = $this->authorizedBatch($validated['invoice_ids']);

        $printer = isset($validated['printer_id'])
            ? PrinterSetting::find($validated['printer_id'])
            : PrinterSetting::getDefault();

        $template = isset($validated['template_id'])
            ? InvoiceTemplate::find($validated['template_id'])
            : null;

        $result = $this->printService->printBatch(
            $invoices->modelKeys(),
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
        Gate::authorize('PrinterSetting:edit');

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
        Gate::authorize('PrintLog:view');

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
        Gate::authorize('PrintLog:view');

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'printer_id' => 'nullable|integer',
        ]);

        $logs = $this->printService->getRecentLogs($validated['limit'] ?? 10, $validated['printer_id'] ?? null);

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
        Gate::authorize('print', $invoice);

        $printerId = $request->get('printer_id');
        $url = $this->printService->getPrintUrl($invoice, $printerId);

        return response()->json([
            'success' => true,
            'url' => $url,
        ]);
    }

    /** @return array<string, string> */
    private function batchRules(): array {
        return [
            'invoice_ids' => 'required|array|min:1|max:' . self::MAX_BATCH,
            'invoice_ids.*' => 'integer|distinct|exists:invoices,id',
        ];
    }

    /**
     * Authorize EVERY invoice before printing ANY of them: one foreign id fails
     * the whole batch with 403 instead of silently printing the rest.
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Invoice>
     */
    private function authorizedBatch(array $ids): Collection {
        $invoices = Invoice::with('appointment')->whereIn('id', $ids)->get();

        foreach ($invoices as $invoice) {
            Gate::authorize('print', $invoice);
        }

        return $invoices;
    }
}
