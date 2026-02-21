{{-- QR Code Line Type --}}
@php
    $size = $properties['size'] ?? 150;
    $alignment = $properties['alignment'] ?? 'center';
    $marginTop = $properties['margin_top'] ?? 10;
    $marginBottom = $properties['margin_bottom'] ?? 5;
    $errorCorrection = $properties['error_correction'] ?? 'M';

    // Generate QR code
    $qrCodeBase64 = $builder->generateQrCode($properties);
@endphp
@if($qrCodeBase64)
<div class="line-item qr-code-container text-{{ $alignment }}"
     style="margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    <img src="data:image/png;base64,{{ $qrCodeBase64 }}"
         alt="QR Code"
         style="width: {{ $size }}px; height: {{ $size }}px;">
</div>
@endif
