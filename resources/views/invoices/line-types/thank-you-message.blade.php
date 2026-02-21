{{-- Thank You Message Line Type --}}
@php
    $message = $properties['message'] ?? 'Thank you for your business!';
    $fontSize = $properties['font_size'] ?? 10;
    $fontStyle = $properties['font_style'] ?? 'italic';
    $alignment = $properties['alignment'] ?? 'center';
    $marginTop = $properties['margin_top'] ?? 10;
    $marginBottom = $properties['margin_bottom'] ?? 5;
@endphp

<div class="line-item thank-you-message text-{{ $alignment }} font-{{ $fontStyle }}"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    {{ $message }}
</div>
