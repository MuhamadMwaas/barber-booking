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
    // When true, the whole row is skipped if the resolved value is empty.
    // Used by the discount / items-total lines so they only appear when a
    // discount was actually granted.
    $hideWhenEmpty = $properties['hide_when_empty'] ?? false;

    // Get value
    $value = '';
    if ($valueType === 'static') {
        $value = $staticValue;
    } elseif ($dynamicField) {
        $value = $builder->resolveDynamicField($dynamicField);
    }
@endphp

@unless($hideWhenEmpty && trim((string) $value) === '')
{{-- Flat flex row (label + value as direct children) so the value is never
     cramped and the label never wraps onto a second line. --}}
<div class="line-item two-column"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    <span class="tc-label @if($labelBold) font-bold @endif">{{ $label }}</span>
    <span class="tc-value">{{ $value }}</span>
</div>
@endunless
