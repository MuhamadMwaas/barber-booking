{{-- Payment Info Line Type --}}
@php
    $showMethod = $properties['show_method'] ?? true;
    $showAmount = $properties['show_amount'] ?? true;
    $showReference = $properties['show_reference'] ?? false;
    $fontSize = $properties['font_size'] ?? 10;
    $alignment = $properties['alignment'] ?? 'center';
    $marginTop = $properties['margin_top'] ?? 5;
    $marginBottom = $properties['margin_bottom'] ?? 5;

    $payment = $invoice->payment;
@endphp

@if($payment)
<div class="line-item payment-info-block text-{{ $alignment }}"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">

    @if($showMethod)
        <div style="font-weight: bold;">
            Payment Method: {{ $payment->method ?? 'Cash' }}
        </div>
    @endif

    @if($showAmount)
        <div style="margin-top: 2px;">
            Amount Paid: {{ number_format($payment->amount ?? 0, 2) }}
        </div>
    @endif

    @if($showReference && $payment->reference)
        <div style="font-size: 0.9em; margin-top: 2px;">
            Ref: {{ $payment->reference }}
        </div>
    @endif
</div>
@endif
