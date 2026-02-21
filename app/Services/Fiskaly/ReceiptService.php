<?php

namespace App\Services\Fiskaly;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReceiptService
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Generate receipt for invoice
     */
    public function generateForInvoice(Invoice $invoice, ?array $fiskalyData = null): string
    {
        // If no Fiskaly data provided, this is an offline receipt
        $isOffline = $fiskalyData === null;

        return view('receipts.pos-receipt', [
            'invoice' => $invoice,
            'fiskaly' => $fiskalyData,
            'isOffline' => $isOffline,
            'business' => $this->getBusinessInfo(),
            'qrCode' => $this->generateQrCode($fiskalyData),
        ])->render();
    }

    /**
     * Generate QR code for receipt
     */
    protected function generateQrCode(?array $fiskalyData): ?string
    {
        if (!$fiskalyData || !isset($fiskalyData['qr_code_data'])) {
            return null;
        }

        if (!config('fiskaly.receipt.include_qr_code', true)) {
            return null;
        }

        try {
            return QrCode::size(200)
                ->format('png')
                ->generate($fiskalyData['qr_code_data']);
        } catch (\Exception $e) {
            Log::error('Failed to generate QR code: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get business information for receipt
     */
    protected function getBusinessInfo(): array
    {
        return [
            'name' => config('fiskaly.receipt.business_name'),
            'address' => config('fiskaly.receipt.business_address'),
            'tax_number' => config('fiskaly.receipt.tax_number'),
            'vat_number' => config('fiskaly.receipt.vat_number'),
        ];
    }

    /**
     * Format signature for receipt display
     */
    public function formatSignature(?array $signature): string
    {
        if (!$signature) {
            return 'Sicherungseinrichtung ausgefallen';
        }

        $parts = [];

        if (isset($signature['value'])) {
            $parts[] = "Signatur: " . substr($signature['value'], 0, 32) . "...";
        }

        if (isset($signature['counter'])) {
            $parts[] = "Signatur-Zähler: " . $signature['counter'];
        }

        return implode("\n", $parts);
    }

    /**
     * Format TSE information for receipt
     */
    public function formatTseInfo(?array $fiskalyData): array
    {
        if (!$fiskalyData) {
            return [
                'status' => 'Offline',
                'message' => 'Sicherungseinrichtung ausgefallen',
            ];
        }

        return [
            'tss_serial' => $fiskalyData['tss_serial_number'] ?? 'N/A',
            'client_serial' => $fiskalyData['client_serial_number'] ?? 'N/A',
            'transaction_number' => $fiskalyData['number'] ?? 'N/A',
            'time_start' => $fiskalyData['time_start'] ?? null,
            'time_end' => $fiskalyData['time_end'] ?? null,
            'signature' => $this->formatSignature($fiskalyData['signature'] ?? null),
        ];
    }

    /**
     * Save receipt as PDF
     */
    public function savePdf(Invoice $invoice, ?array $fiskalyData = null): string
    {
        $html = $this->generateForInvoice($invoice, $fiskalyData);
        return $html;
        // // Use a PDF library like DomPDF or SnappyPDF
        // $pdf = \PDF::loadHTML($html);

        // $filename = "receipt-{$invoice->invoice_number}.pdf";
        // $path = "receipts/{$invoice->created_at->format('Y/m')}/{$filename}";

        // Storage::disk('local')->put($path, $pdf->output());

        // return $path;
    }

    /**
     * Print receipt (send to printer)
     */
    public function print(Invoice $invoice, ?array $fiskalyData = null): bool
    {
        // This would integrate with your thermal printer
        // For now, we'll just generate the content

        $content = $this->generateForInvoice($invoice, $fiskalyData);

        // TODO: Integrate with thermal printer library
        // e.g., mike42/escpos-php for ESC/POS printers

        return true;
    }

    /**
     * Generate thermal printer compatible receipt
     */
    public function generateThermalReceipt(Invoice $invoice, ?array $fiskalyData = null): string
    {
        $business = $this->getBusinessInfo();
        $tseInfo = $this->formatTseInfo($fiskalyData);
        $isOffline = $fiskalyData === null;

        $receipt = "";

        // Header
        $receipt .= $this->center($business['name']) . "\n";
        $receipt .= $this->center($business['address']) . "\n";
        $receipt .= $this->line() . "\n";

        // Tax info
        if ($business['tax_number']) {
            $receipt .= "Steuernummer: {$business['tax_number']}\n";
        }
        if ($business['vat_number']) {
            $receipt .= "USt-IdNr.: {$business['vat_number']}\n";
        }
        $receipt .= $this->line() . "\n";

        // Invoice info
        $receipt .= "Beleg: {$invoice->invoice_number}\n";
        $receipt .= "Datum: " . $invoice->created_at->format('d.m.Y H:i') . "\n";
        $receipt .= $this->line() . "\n";

        // Items
        foreach ($invoice->items as $item) {
            $receipt .= $this->formatLine(
                $item->description,
                number_format($item->subtotal, 2, ',', '.') . ' €'
            ) . "\n";
            $receipt .= "  {$item->quantity}x " .
                       number_format($item->unit_price, 2, ',', '.') . " €\n";
        }

        $receipt .= $this->line() . "\n";

        // Totals
        $receipt .= $this->formatLine('Netto:',
            number_format($invoice->subtotal, 2, ',', '.') . ' €') . "\n";
        $receipt .= $this->formatLine("MwSt {$invoice->tax_rate}%:",
            number_format($invoice->tax_amount, 2, ',', '.') . ' €') . "\n";
        $receipt .= $this->formatLine('GESAMT:',
            number_format($invoice->total_amount, 2, ',', '.') . ' €', true) . "\n";

        $receipt .= $this->line() . "\n";

        // Payment method
        $paymentMethod = $invoice->payments->first()?->payment_method ?? 'Bar';
        $receipt .= $this->formatLine('Zahlungsart:', $paymentMethod) . "\n";

        $receipt .= $this->line() . "\n";

        // TSE Information (REQUIRED by KassenSichV)
        if ($isOffline) {
            $receipt .= "** Sicherungseinrichtung ausgefallen **\n";
            $receipt .= "Dieser Beleg wurde ohne TSE erstellt.\n";
        } else {
            $receipt .= "TSE-Seriennummer:\n{$tseInfo['tss_serial']}\n";
            $receipt .= "Kassen-Seriennummer:\n{$tseInfo['client_serial']}\n";
            $receipt .= "Transaktionsnummer: {$tseInfo['transaction_number']}\n";
            $receipt .= "Start: " . $this->formatDateTime($tseInfo['time_start']) . "\n";
            $receipt .= "Ende: " . $this->formatDateTime($tseInfo['time_end']) . "\n";
            $receipt .= "\n{$tseInfo['signature']}\n";
        }

        $receipt .= $this->line() . "\n";
        $receipt .= $this->center("Vielen Dank für Ihren Besuch!") . "\n";
        $receipt .= "\n\n\n";

        return $receipt;
    }

    /**
     * Helper: Format line with left and right alignment
     */
    protected function formatLine(string $left, string $right, bool $bold = false): string
    {
        $width = 42; // Standard thermal printer width
        $spacing = $width - mb_strlen($left) - mb_strlen($right);
        $spacing = max(1, $spacing);

        return $left . str_repeat(' ', $spacing) . $right;
    }

    /**
     * Helper: Center text
     */
    protected function center(string $text): string
    {
        $width = 42;
        $padding = floor(($width - mb_strlen($text)) / 2);
        return str_repeat(' ', $padding) . $text;
    }

    /**
     * Helper: Create line separator
     */
    protected function line(): string
    {
        return str_repeat('-', 42);
    }

    /**
     * Helper: Format datetime
     */
    protected function formatDateTime(?string $datetime): string
    {
        if (!$datetime) {
            return 'N/A';
        }

        try {
            return \Carbon\Carbon::parse($datetime)->format('d.m.Y H:i:s');
        } catch (\Exception $e) {
            return $datetime;
        }
    }
}
