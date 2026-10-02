<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceTemplate\TemplateBuilderService;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * The customer's receipt as HTML, opened from the app (AUTHZ-03).
 *
 * The mobile app authenticates with a Sanctum bearer token, which a WebView or
 * browser cannot send, so this route is reached through a temporary signed URL
 * handed out by the authenticated API (CustomerInvoiceResource::view_url).
 *
 * The signature pins BOTH the invoice and the customer it was issued to, and the
 * same InvoicePolicy::view() rule is re-checked here for that customer — so a
 * link stops working if the invoice changes hands or leaves the PAID state, not
 * only when it expires.
 *
 * Read-only by design: it renders the template directly (no PrintService), so
 * no PrintLog, no print_count, no auto-print script — and the receipt is
 * always marked as a copy.
 */
class InvoiceCopyController extends Controller
{
    public const LINK_TTL_MINUTES = 15;

    public static function signedUrl(Invoice $invoice, DateTimeInterface $expiresAt): string
    {
        return URL::temporarySignedRoute('invoice.customer-copy', $expiresAt, [
            'invoice' => $invoice->id,
            'customer' => $invoice->customer_id,
        ]);
    }

    /**
     * GET /my/invoices/{invoice}/view?customer=…&expires=…&signature=…
     */
    public function show(Request $request, Invoice $invoice, TemplateBuilderService $builder)
    {
        $customer = User::find($request->integer('customer'));

        abort_unless($customer && Gate::forUser($customer)->allows('view', $invoice), 404);

        $invoice->renderAsCustomerCopy = true;

        try {
            $html = $builder->build($invoice);
        } catch (\Throwable $e) {
            report($e);

            return response(__('Unable to display the invoice.'), 500);
        }

        return response($html)
            ->header('Content-Type', 'text/html')
            // A receipt carries personal data: keep it out of shared caches.
            ->header('Cache-Control', 'private, no-store');
    }
}
