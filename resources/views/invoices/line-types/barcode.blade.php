{{-- Barcode Line Type --}}
@php
    $type = $properties['type'] ?? 'code128';
    $height = $properties['height'] ?? 50;
    $alignment = $properties['alignment'] ?? 'center';
    $showText = $properties['show_text'] ?? true;
    $marginTop = $properties['margin_top'] ?? 5;
    $marginBottom = $properties['margin_bottom'] ?? 5;

    $barcodeValue = $invoice->invoice_number ?? 'DRAFT';
@endphp

<div class="line-item barcode-container text-{{ $alignment }}"
     style="margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">

    {{-- Simple barcode representation (you can integrate a barcode library) --}}
    <div style="font-family: 'Courier New', monospace; font-size: 1.2em; letter-spacing: 2px; margin-bottom: 5px;">
        {{ $barcodeValue }}
    </div>

    @if($showText)
        <div style="font-size: 0.85em; margin-top: 3px;">
            {{ $barcodeValue }}
        </div>
    @endif

    {{--
    Note: For real barcode generation, you can use:
    - milon/barcode package
    - Or generate SVG barcode here

    Example with milon/barcode:
    <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($barcodeValue, $type, 2, $height) }}" alt="barcode">
    --}}
</div>
