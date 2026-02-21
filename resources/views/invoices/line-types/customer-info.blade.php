{{-- Customer Info Line Type --}}
@php
    $showName = $properties['show_name'] ?? true;
    $showEmail = $properties['show_email'] ?? true;
    $showPhone = $properties['show_phone'] ?? true;
    $showAddress = $properties['show_address'] ?? false;
    $fontSize = $properties['font_size'] ?? 9;
    $marginTop = $properties['margin_top'] ?? 5;
    $marginBottom = $properties['margin_bottom'] ?? 5;

    $customer = $invoice->customer;
@endphp

@if($customer)
<div class="line-item customer-info-block"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    <div style="font-weight: bold; margin-bottom: 3px;">Customer:</div>

    @if($showName && $customer->name)
        <div>{{ $customer->name }}</div>
    @endif

    @if($showEmail && $customer->email)
        <div style="font-size: 0.95em;">{{ $customer->email }}</div>
    @endif

    @if($showPhone && $customer->phone)
        <div style="font-size: 0.95em;">{{ $customer->phone }}</div>
    @endif

    @if($showAddress && $customer->address)
        <div style="font-size: 0.95em; margin-top: 2px;">{{ $customer->address }}</div>
    @endif
</div>
@endif
