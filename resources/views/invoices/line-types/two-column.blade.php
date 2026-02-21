{{-- Two Column Line Type --}}
@php
    $label = $properties['label'] ?? 'Label:';
    $labelWidth = $properties['label_width'] ?? 50;
    $valueType = $properties['value_type'] ?? 'static';
    $staticValue = $properties['static_value'] ?? '';
    $dynamicField = $properties['dynamic_field'] ?? null;
    $fontSize = $properties['font_size'] ?? 10;
    $labelBold = $properties['label_bold'] ?? true;
    $alignment = $properties['alignment'] ?? 'left';
    $marginTop = $properties['margin_top'] ?? 0;
    $marginBottom = $properties['margin_bottom'] ?? 2;

    // Get value
    $value = '';
    if ($valueType === 'static') {
        $value = $staticValue;
    } elseif ($dynamicField) {
        $value = $builder->resolveDynamicField($dynamicField);
    }
@endphp

<div class="line-item two-column text-{{ $alignment }}"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    <div style="display: flex; justify-content: space-between;">
        <span @if($labelBold) class="font-bold" @endif style="width: {{ $labelWidth }}%;">
            {{ $label }}
        </span>
        <span style="width: {{ 100 - $labelWidth }}%; text-align: right;">
            {{ $value }}
        </span>
    </div>
</div>
