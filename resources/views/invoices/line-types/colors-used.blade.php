{{-- Colors Used Line Type --}}
{{-- Displays colors used in the appointment as informational data on the invoice --}}
@php
    $title       = $properties['title']         ?? 'Colors Used';
    $showTitle   = $properties['show_title']    ?? true;
    $showHex     = $properties['show_hex']      ?? true;
    $showBrand   = $properties['show_brand']    ?? true;
    $fontSize    = $properties['font_size']     ?? 9;
    $marginTop   = $properties['margin_top']    ?? 5;
    $marginBottom= $properties['margin_bottom'] ?? 5;

    $colorRecords = $invoice->appointment?->colorRecords ?? collect();
@endphp

@if ($colorRecords->isNotEmpty())
<div class="line-item colors-used-container"
     style="margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px; font-size: {{ $fontSize }}px;">

    @if ($showTitle)
        <div style="font-weight: bold; margin-bottom: 4px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px;">
            {{ $title }}
        </div>
    @endif

    <table style="width: 100%; border-collapse: collapse;">
        <tbody>
            @foreach ($colorRecords as $colorRecord)
                @php $color = $colorRecord->color; @endphp
                @if ($color)
                    <tr>
                        <td style="padding: 2px 4px; vertical-align: middle; width: 18px;">
                            @if ($showHex)
                                <span style="display: inline-block; width: 12px; height: 12px;
                                             background: {{ $color->hex_code }};
                                             border: 1px solid #ccc;
                                             border-radius: 2px; vertical-align: middle;"></span>
                            @endif
                        </td>
                        <td style="padding: 2px 4px; vertical-align: middle;">
                            {{ $color->name }}
                            @if ($showBrand && $color->brand)
                                <span style="color: #94a3b8;"> ({{ $color->brand }})</span>
                            @endif
                        </td>
                        <td style="padding: 2px 4px; text-align: right; vertical-align: middle;">
                            {{ number_format($colorRecord->quantity, 2) }} {{ $color->unit }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
@endif
