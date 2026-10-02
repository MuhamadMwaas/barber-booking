<?php

namespace App\Http\Controllers\Api;

use App\Enum\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerInvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A customer's own receipts, read-only (AUTHZ-03).
 *
 * Nothing here prints: no PrintLog, no print_count, no "Kopie N". The HTML
 * version is reached through the short-lived signed `view_url` in each item,
 * served by InvoiceCopyController.
 */
class MyInvoiceController extends Controller
{
    /**
     * GET /api/my/invoices
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $invoices = $this->ownPaidInvoices($request)
            ->with(['items', 'appointment'])
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return CustomerInvoiceResource::collection($invoices);
    }

    /**
     * GET /api/my/invoices/{id}
     *
     * Someone else's invoice answers 404, exactly like one that does not exist,
     * so the endpoint cannot be used to probe which invoice ids are real.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->ownPaidInvoices($request)
            ->with(['items', 'appointment'])
            ->findOrFail($id);

        return (new CustomerInvoiceResource($invoice))->response();
    }

    /**
     * The same rule as InvoicePolicy::view(), as a query. The policy stays the
     * authority (the signed HTML view checks it); this only keeps the list and
     * the 404 consistent with it.
     */
    private function ownPaidInvoices(Request $request): Builder
    {
        return Invoice::query()
            ->where('customer_id', $request->user()->id)
            ->where('status', InvoiceStatus::PAID);
    }
}
