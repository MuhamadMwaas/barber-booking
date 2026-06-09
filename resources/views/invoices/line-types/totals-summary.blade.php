{{-- Totals Summary Line Type --}}
@php
    $showSubtotal = $properties['show_subtotal'] ?? true;
    $showTaxBreakdown = $properties['show_tax_breakdown'] ?? true;
    $showTotal = $properties['show_total'] ?? true;
    $highlightTotal = $properties['highlight_total'] ?? true;
    $fontSize = $properties['font_size'] ?? 10;
    $totalFontSize = $properties['total_font_size'] ?? 12;
    $alignment = $properties['alignment'] ?? 'right';
    $marginTop = $properties['margin_top'] ?? 5;
    $marginBottom = $properties['margin_bottom'] ?? 5;

    // Discount is GROSS (tax-inclusive). When > 0 we show the pre-discount items
    // total and the discount line; the Net/Tax/Total below already reflect the
    // discounted amount (reconciled by InvoiceService::applyFinalAmount).
    $discountAmount = (float) ($invoice->discount_amount ?? 0);
    $hasDiscount = $discountAmount > 0;
    $itemsTotal = (float) ($invoice->total_amount ?? 0) + $discountAmount;
@endphp

<div class="line-item totals-summary"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">

    @if($hasDiscount)
        <div class="totals-row">
            <span>{{ __('invoice_template.items_total') }}:</span>
            <span>{{ number_format($itemsTotal, 2) }}</span>
        </div>
        <div class="totals-row">
            <span>{{ __('invoice_template.discount') }}:</span>
            <span>-{{ number_format($discountAmount, 2) }}</span>
        </div>
    @endif

    @if($showSubtotal)
        <div class="totals-row">
            <span>{{ __('invoice_template.subtotal_net') }}:</span>
            <span>{{ number_format($invoice->subtotal ?? 0, 2) }}</span>
        </div>
    @endif

    @if($showTaxBreakdown)
        <div class="totals-row">
            <span>{{ __('invoice_template.tax') }} {{ number_format($invoice->tax_rate ?? 0, 0) }}%:</span>
            <span>{{ number_format($invoice->tax_amount ?? 0, 2) }}</span>
        </div>
    @endif

    @if($showTotal)
        <div class="totals-row @if($highlightTotal) highlight @endif"
             style="@if($highlightTotal) font-size: {{ $totalFontSize }}px; @endif">
            <span>{{ __('invoice_template.total') }}:</span>
            <span>{{ number_format($invoice->total_amount ?? 0, 2) }}</span>
        </div>
    @endif
</div>
