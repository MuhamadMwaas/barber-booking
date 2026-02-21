{{-- Separator Line Type --}}
@php
    $style = $properties['style'] ?? 'solid'; // solid, dashed, dotted
    $width = $properties['width'] ?? 1;
    $color = $properties['color'] ?? '#000000';
    $marginTop = $properties['margin_top'] ?? 3;
    $marginBottom = $properties['margin_bottom'] ?? 3;
@endphp

<hr class="line-item separator-line separator-{{ $style }}"
    style="border-top-width: {{ $width }}px; border-top-color: {{ $color }}; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
