{{-- Invoice Number Line Type --}}
@php
    $label = $properties['label'] ?? 'Invoice No:';
    $showLabel = $properties['show_label'] ?? true;
    $fontSize = $properties['font_size'] ?? 10;
    $fontWeight = $properties['font_weight'] ?? 'normal';
    $alignment = $properties['alignment'] ?? 'left';
    $marginTop = $properties['margin_top'] ?? 0;
    $marginBottom = $properties['margin_bottom'] ?? 2;

    $invoiceNumber = $invoice->invoice_number ?? 'DRAFT';
@endphp

<div class="line-item invoice-number text-{{ $alignment }} font-{{ $fontWeight }}"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    @if($showLabel)
        <strong>{{ $label }}</strong> {{ $invoiceNumber }}
    @else
        {{ $invoiceNumber }}
    @endif
</div>
