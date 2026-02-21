{{-- TSE/Fiskaly Info Line Type --}}
@php
    $showTssSerial = $properties['show_tss_serial'] ?? true;
    $showTransactionNumber = $properties['show_transaction_number'] ?? true;
    $showSignatureCounter = $properties['show_signature_counter'] ?? true;
    $showTimestamp = $properties['show_timestamp'] ?? true;
    $fontSize = $properties['font_size'] ?? 7;
    $alignment = $properties['alignment'] ?? 'center';
    $marginTop = $properties['margin_top'] ?? 5;
    $marginBottom = $properties['margin_bottom'] ?? 5;

    $fiskalyData = $invoice->invoice_data ?? [];
@endphp

@if(!empty($fiskalyData))
<div class="line-item tse-info text-{{ $alignment }}"
     style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">

    <div style="margin-bottom: 3px; font-weight: bold;">TSE Information</div>

    @if($showTssSerial && isset($fiskalyData['fiskaly_tss_serial']))
        <div>TSS Serial: {{ Str::limit($fiskalyData['fiskaly_tss_serial'], 30) }}</div>
    @endif

    @if($showTransactionNumber && isset($fiskalyData['fiskaly_transaction_number']))
        <div>Transaction: {{ $fiskalyData['fiskaly_transaction_number'] }}</div>
    @endif

    @if($showSignatureCounter && isset($fiskalyData['fiskaly_signature']['counter']))
        <div>Signature Counter: {{ $fiskalyData['fiskaly_signature']['counter'] }}</div>
    @endif

    @if($showTimestamp && isset($fiskalyData['fiskaly_time_start']))
        <div>Time: {{ date('Y-m-d H:i:s', $fiskalyData['fiskaly_time_start']) }}</div>
    @endif

    @if(isset($fiskalyData['fiskaly_client_serial']))
        <div style="margin-top: 2px;">Client: {{ $fiskalyData['fiskaly_client_serial'] }}</div>
    @endif
</div>
@endif
