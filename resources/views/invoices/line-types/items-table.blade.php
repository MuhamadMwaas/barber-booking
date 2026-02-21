{{-- Items Table Line Type --}}
@php
    $showItemNumbers = $properties['show_item_numbers'] ?? true;
    $showQuantity = $properties['show_quantity'] ?? true;
    $showUnitPrice = $properties['show_unit_price'] ?? true;
    $showTaxRate = $properties['show_tax_rate'] ?? true;
    $showTaxAmount = $properties['show_tax_amount'] ?? false;
    $showTotal = $properties['show_total'] ?? true;
    $tableBorder = $properties['table_border'] ?? true;
    $headerBackground = $properties['header_background'] ?? '#000000';
    $headerTextColor = $properties['header_text_color'] ?? '#ffffff';
    $rowSeparator = $properties['row_separator'] ?? true;
    $fontSize = $properties['font_size'] ?? 9;
    $marginTop = $properties['margin_top'] ?? 5;
    $marginBottom = $properties['margin_bottom'] ?? 5;
@endphp

<div class="line-item items-table-container" style="margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px; font-size: {{ $fontSize }}px;">
    <table class="items-table {{ $tableBorder ? 'bordered' : '' }} {{ $rowSeparator ? 'row-separator' : '' }}">
        <thead>
            <tr style="background-color: {{ $headerBackground }}; color: {{ $headerTextColor }};">
                @if($showItemNumbers)
                    <th style="width: 8%;">#</th>
                @endif

                <th style="width: {{ $showItemNumbers ? '40%' : '48%' }};">Description</th>

                @if($showQuantity)
                    <th style="width: 10%;" class="text-center">Qty</th>
                @endif

                @if($showUnitPrice)
                    <th style="width: 15%;" class="text-right">Price</th>
                @endif

                @if($showTaxRate)
                    <th style="width: 12%;" class="text-right">Tax</th>
                @endif

                @if($showTaxAmount)
                    <th style="width: 15%;" class="text-right">Tax €</th>
                @endif

                @if($showTotal)
                    <th style="width: 15%;" class="text-right">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $index => $item)
                <tr>
                    @if($showItemNumbers)
                        <td>{{ $index + 1 }}</td>
                    @endif

                    <td>{{ $item->description }}</td>

                    @if($showQuantity)
                        <td class="text-center">{{ $item->quantity }}</td>
                    @endif

                    @if($showUnitPrice)
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    @endif

                    @if($showTaxRate)
                        <td class="text-right">{{ number_format($item->tax_rate, 0) }}%</td>
                    @endif

                    @if($showTaxAmount)
                        <td class="text-right">{{ number_format($item->tax_amount, 2) }}</td>
                    @endif

                    @if($showTotal)
                        <td class="text-right"><strong>{{ number_format($item->total_amount, 2) }}</strong></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
