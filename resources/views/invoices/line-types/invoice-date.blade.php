{{-- Invoice Date Line Type --}}
@php
    $label = $properties['label'] ?? 'Date:';
    $showLabel = $properties['show_label'] ?? true;
    $showTime = $properties['show_time'] ?? true;
    $format = $properties['format'] ?? 'd.m.Y H:i';
    $fontSize = $properties['font_size'] ?? 10;
    $alignment = $properties['alignment'] ?? 'left';
    $marginTop = $properties['margin_top'] ?? 0;
    $marginBottom = $properties['margin_bottom'] ?? 2;

    $date = $invoice->created_at?->format($format) ?? now()->format($format);
@endphp

<div class="line-item invoice-date text-{{ $alignment }}"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    @if($showLabel)
        <strong>{{ $label }}</strong> {{ $date }}
    @else
        {{ $date }}
    @endif
</div>
