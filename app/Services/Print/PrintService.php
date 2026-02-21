<?php

namespace App\Services\Print;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\PrinterSetting;
use App\Models\PrintLog;
use App\Services\InvoiceTemplate\TemplateBuilderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrintService {
    protected TemplateBuilderService $builder;

    public function __construct() {
        $this->builder = new TemplateBuilderService();
    }

    /**
     * Print invoice to browser (main method)
     */
    public function print(
        Invoice $invoice,
        ?PrinterSetting $printer = null,
        ?InvoiceTemplate $template = null,
        int $copies = 1
    ): array {
        try {
            // Get printer
            $printer = $printer ?? PrinterSetting::getDefault();

            if (!$printer) {
                throw new \Exception('No printer configured. Please add a printer in settings.');
            }

            if (!$printer->is_active) {
                throw new \Exception('Selected printer is not active.');
            }

            // Get template
            $template = $template ?? $invoice->getTemplateOrDefault();

            if (!$template) {
                throw new \Exception('No template available for this invoice.');
            }

            // Create print log
            $printLog = $this->createPrintLog($invoice, $printer, $template, $copies);
            $printLog->markAsStarted();

            // Get print number and copy label
            $printNumber = $invoice->getNextPrintNumber();
            $copyLabel = $invoice->getCopyLabel();
            // Build HTML
            $html = $this->buildPrintHtml($invoice, $template, $printer, $copyLabel);

            // Increment print count
            $invoice->incrementPrintCount();

            // Mark as success
            $printLog->markAsSuccess();

            return [
                'success' => true,
                'html' => $html,
                'print_log_id' => $printLog->id,
                'print_number' => $printNumber,
                'copy_label' => $copyLabel,
                'printer' => $printer->name,
            ];
        } catch (\Exception $e) {
            Log::error('Print failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($printLog)) {
                $printLog->markAsFailed($e->getMessage());
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build HTML with auto-print script
     */
    protected function buildPrintHtml(
        Invoice $invoice,
        InvoiceTemplate $template,
        PrinterSetting $printer,
        string $copyLabel
    ): string {

        // Build base HTML
        $html = $this->builder->build($invoice, $template);

        // Inject copy label into invoice number
        if ($copyLabel) {
            $html = $this->injectCopyLabel($html, $copyLabel);
        }

        // Add auto-print script
        $html = $this->addAutoPrintScript($html, $printer);

        return $html;
    }

    /**
     * Inject COPY label into HTML
     */
    protected function injectCopyLabel(string $html, string $copyLabel): string {
        // Find invoice number pattern and add copy label
        // Pattern: Rechnung Nr. 749
        $pattern = '/(Rechnung Nr\.\s*\d+)/i';
        $replacement = '$1 ' . $copyLabel;

        return preg_replace($pattern, $replacement, $html, 1);
    }

    /**
     * Add auto-print JavaScript
     */
    protected function addAutoPrintScript(string $html, PrinterSetting $printer): string {
        $script = <<<'SCRIPT'
<script>
(function() {
    let printed = false;

    function autoPrint() {
        if (printed) return;

        try {
            // Give browser time to render
            setTimeout(() => {
                window.print();
                printed = true;

                // Optional: close window after print dialog closes
                // Uncomment if you want auto-close
                // setTimeout(() => window.close(), 1000);
            }, 500);
        } catch (error) {
            console.error('Auto-print failed:', error);
        }
    }

    // Auto-print when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoPrint);
    } else {
        autoPrint();
    }

    // Fallback: manual print button
    window.manualPrint = function() {
        window.print();
    };
})();
</script>

<!-- Fallback manual print button -->
<div style="position: fixed; top: 10px; right: 10px; z-index: 9999; display: none;" id="print-button" class="no-print">
    <button onclick="manualPrint()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
        🖨️ Print
    </button>
</div>

<script>
// Show manual button if auto-print fails
setTimeout(() => {
    if (!window.printed) {
        document.getElementById('print-button').style.display = 'block';
    }
}, 2000);
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
SCRIPT;

        // Insert before </body>
        return str_replace('</body>', $script . '</body>', $html);
    }

    /**
     * Create print log entry
     */
    protected function createPrintLog(
        Invoice $invoice,
        PrinterSetting $printer,
        InvoiceTemplate $template,
        int $copies
    ): PrintLog {
        return PrintLog::create([
            'invoice_id' => $invoice->id,
            'template_id' => $template->id,
            'printer_id' => $printer->id,
            'user_id' => Auth::user()->id,
            'print_number' => $invoice->getNextPrintNumber(),
            'copies' => $copies,
            'print_type' => $invoice->isPrinted() ? 'copy' : 'original',
            'status' => 'pending',
            'print_data' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'printer_name' => $printer->name,
                'template_name' => $template->name,
            ],
        ]);
    }

    /**
     * Get print URL for invoice
     */
    public function getPrintUrl(Invoice $invoice, ?int $printerId = null): string {
        $params = ['invoice' => $invoice->id];

        if ($printerId) {
            $params['printer_id'] = $printerId;
        }

        return route('invoice.print', $params);
    }

    /**
     * Batch print multiple invoices
     */
    public function printBatch(
        array $invoiceIds,
        ?PrinterSetting $printer = null,
        ?InvoiceTemplate $template = null
    ): array {
        $printer = $printer ?? PrinterSetting::getDefault();

        if (!$printer) {
            return [
                'success' => false,
                'error' => 'No printer configured',
            ];
        }

        $invoices = Invoice::with(['customer', 'items', 'payment', 'appointment'])
            ->whereIn('id', $invoiceIds)
            ->get();

        $htmlPages = [];
        $results = [];

        foreach ($invoices as $invoice) {
            $result = $this->print($invoice, $printer, $template);

            if ($result['success']) {
                $htmlPages[] = $result['html'];
            }

            $results[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? $invoice->id,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ];
        }

        // Combine with page breaks
        $combinedHtml = implode('<div style="page-break-after: always;"></div>', $htmlPages);

        return [
            'success' => true,
            'html' => $combinedHtml,
            'results' => $results,
            'total' => count($invoiceIds),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
        ];
    }

    /**
     * Test printer connection
     */
    public function testPrinter(PrinterSetting $printer): array {
        return $printer->testConnection();
    }

    /**
     * Get print statistics
     */
    public function getStatistics(?int $printerId = null): array {
        $query = PrintLog::query();

        if ($printerId) {
            $query->where('printer_id', $printerId);
        }

        return [
            'total_prints' => $query->count(),
            'successful_prints' => $query->where('status', 'success')->count(),
            'failed_prints' => $query->where('status', 'failed')->count(),
            'prints_today' => $query->whereDate('created_at', today())->count(),
            'average_duration_ms' => $query->where('status', 'success')->avg('duration_ms'),
        ];
    }

    /**
     * Get recent print logs
     */
    public function getRecentLogs(int $limit = 10, ?int $printerId = null): \Illuminate\Support\Collection {
        $query = PrintLog::with(['invoice', 'printer', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($printerId) {
            $query->where('printer_id', $printerId);
        }

        return $query->get();
    }
}
