<?php

namespace App\Http\Resources;

use App\Http\Controllers\InvoiceCopyController;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice as its CUSTOMER sees it (GET /api/my/invoices).
 *
 * Deliberately NOT exposed: `invoice_data` (TSE/payment internals), `notes`
 * (staff notes), the signature fields and the print counters.
 */
class CustomerInvoiceResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $expiresAt = now()->addMinutes(InvoiceCopyController::LINK_TTL_MINUTES);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->name,
            'status_value' => $this->status->value,
            'issued_at' => $this->created_at?->format('Y-m-d H:i:s'),

            'subtotal' => (float) $this->subtotal,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            // Tip is outside the total (no VAT, not revenue); what the customer
            // actually handed over is total + tip.
            'tip_amount' => (float) $this->tip_amount,
            'paid_amount' => (float) $this->total_amount + (float) $this->tip_amount,

            'appointment' => $this->whenLoaded('appointment', fn () => $this->appointment ? [
                'id' => $this->appointment->id,
                'number' => $this->appointment->number,
                'appointment_date' => $this->appointment->appointment_date?->format('Y-m-d'),
            ] : null),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'tax_amount' => (float) $item->tax_amount,
                'total_amount' => (float) $item->total_amount,
            ])->values()),

            // Opens the receipt (HTML, marked "Kopie") in a WebView / browser. The
            // link is a short-lived bearer: fetch a fresh one each time instead of
            // storing it.
            'view_url' => InvoiceCopyController::signedUrl($this->resource, $expiresAt),
            'view_url_expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ];
    }
}
