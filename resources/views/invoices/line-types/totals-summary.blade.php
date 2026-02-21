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
@endphp

<div class="line-item totals-summary"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">

    @if($showSubtotal)
        <div class="totals-row">
            <span>Subtotal (Net):</span>
            <span>{{ number_format($invoice->subtotal ?? 0, 2) }}</span>
        </div>
    @endif

    @if($showTaxBreakdown)
        <div class="totals-row">
            <span>Tax {{ number_format($invoice->tax_rate ?? 0, 0) }}%:</span>
            <span>{{ number_format($invoice->tax_amount ?? 0, 2) }}</span>
        </div>
    @endif

    @if($showTotal)
        <div class="totals-row @if($highlightTotal) highlight @endif"
             style="@if($highlightTotal) font-size: {{ $totalFontSize }}px; @endif">
            <span>Total:</span>
            <span>{{ number_format($invoice->total_amount ?? 0, 2) }}</span>
        </div>
    @endif
</div>
