{{-- Text Line Type --}}
@php
    $contentType = $properties['content_type'] ?? 'static';
    $value = '';

    if ($contentType === 'static') {
        $value = $properties['static_value'] ?? '';
    } else {
        $dynamicField = $properties['dynamic_field'] ?? null;
        if ($dynamicField) {
            $value = $builder->resolveDynamicField($dynamicField);
        }
    }

    $prefix = $properties['prefix'] ?? '';
    $suffix = $properties['suffix'] ?? '';
    $fontSize = $properties['font_size'] ?? 10;
    $fontWeight = $properties['font_weight'] ?? 'normal';
    $fontStyle = $properties['font_style'] ?? 'normal';
    $alignment = $properties['alignment'] ?? 'left';
    $color = $properties['color'] ?? '#000000';
    $marginTop = $properties['margin_top'] ?? 0;
    $marginBottom = $properties['margin_bottom'] ?? 2;
@endphp

<div class="line-item text-line text-{{ $alignment }} font-{{ $fontWeight }} font-{{ $fontStyle }}"
     style="font-size: {{ $fontSize }}px; color: {{ $color }}; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    {{ $prefix }}{{ $value }}{{ $suffix }}
</div>
