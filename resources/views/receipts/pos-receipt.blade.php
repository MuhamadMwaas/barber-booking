<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال - {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
            font-size: 12px;
            line-height: 1.4;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
        }

        .receipt-header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-header p {
            font-size: 11px;
            margin: 2px 0;
        }

        .receipt-info {
            margin-bottom: 15px;
            font-size: 11px;
        }

        .receipt-info .row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        .items-table {
            width: 100%;
            margin-bottom: 15px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .items-table .header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            padding: 5px 0;
            border-bottom: 1px solid #000;
        }

        .items-table .item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 11px;
        }

        .items-table .item-name {
            flex: 1;
        }

        .items-table .item-price {
            width: 60px;
            text-align: left;
        }

        .totals {
            margin-bottom: 15px;
            font-size: 11px;
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        .totals .total-row {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        .payment-info {
            margin-bottom: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 11px;
        }

        .fiskaly-section {
            margin-top: 15px;
            padding: 10px 5px;
            border: 2px solid #000;
            background: #f9f9f9;
        }

        .fiskaly-section h3 {
            font-size: 12px;
            text-align: center;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .fiskaly-section .info {
            font-size: 9px;
            line-height: 1.5;
        }

        .fiskaly-section .info .row {
            margin: 2px 0;
            word-wrap: break-word;
        }

        .qr-code {
            text-align: center;
            margin: 10px 0;
        }

        .qr-code img {
            width: 120px;
            height: 120px;
        }

        .signature {
            font-size: 8px;
            word-break: break-all;
            background: #fff;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px dashed #000;
            font-size: 10px;
        }

        .footer p {
            margin: 3px 0;
        }

        @media print {
            body {
                width: 80mm;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="receipt-header">
        <h1>{{ config('app.name', 'صالون التجميل') }}</h1>
        <p>{{ config('fiskaly.business.name') }}</p>
        <p>{{ config('fiskaly.business.address') ?? 'العنوان' }}</p>
        <p>{{ config('fiskaly.business.phone') ?? 'الهاتف' }}</p>
        @if(config('fiskaly.business.tax_number'))
        <p>الرقم الضريبي: {{ config('fiskaly.business.tax_number') }}</p>
        @endif
    </div>

    <!-- Invoice Info -->
    <div class="receipt-info">
        <div class="row">
            <span>رقم الفاتورة:</span>
            <strong>{{ $invoice->invoice_number }}</strong>
        </div>
        <div class="row">
            <span>التاريخ:</span>
            <span>{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
        </div>
        @if($invoice->customer)
        <div class="row">
            <span>العميل:</span>
            <span>{{ $invoice->customer->name }}</span>
        </div>
        @endif
        @if($invoice->branch)
        <div class="row">
            <span>الفرع:</span>
            <span>{{ $invoice->branch->name }}</span>
        </div>
        @endif
    </div>

    <!-- Items -->
    <div class="items-table">
        <div class="header">
            <div class="item-name">الخدمة</div>
            <div class="item-price">السعر</div>
        </div>

        @foreach($invoice->items as $item)
        <div class="item">
            <div class="item-name">
                {{ $item->description ?? $item->service->name ?? 'خدمة' }}
                @if($item->provider)
                <br><small style="font-size: 9px;">{{ $item->provider->name }}</small>
                @endif
            </div>
            <div class="item-price">{{ number_format($item->subtotal, 2) }} €</div>
        </div>
        @endforeach
    </div>

    <!-- Totals -->
    <div class="totals">
        <div class="row">
            <span>المجموع الفرعي:</span>
            <span>{{ number_format($invoice->subtotal, 2) }} €</span>
        </div>

        @if($invoice->discount_amount > 0)
        <div class="row">
            <span>الخصم:</span>
            <span>-{{ number_format($invoice->discount_amount, 2) }} €</span>
        </div>
        @endif

        <div class="row">
            <span>الضريبة ({{ $invoice->tax_rate }}%):</span>
            <span>{{ number_format($invoice->tax_amount, 2) }} €</span>
        </div>

        <div class="row total-row">
            <span>المجموع الكلي:</span>
            <span>{{ number_format($invoice->total_amount, 2) }} €</span>
        </div>
    </div>

    <!-- Payment Info -->
    @if($invoice->payments->count() > 0)
    <div class="payment-info">
        <strong>طريقة الدفع:</strong>
        @foreach($invoice->payments as $payment)
        <div class="row">
            <span>{{ $payment->payment_method == 'cash' ? 'نقداً' : 'بطاقة' }}</span>
            <span>{{ number_format($payment->amount, 2) }} €</span>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Fiskaly TSE Section -->
    @if(!empty($fiskalyData))
    <div class="fiskaly-section">
        <h3>معلومات الأمان الضريبي (TSE)</h3>

        <div class="info">
            @if(!empty($fiskalyData['transaction_number']))
            <div class="row">
                <strong>رقم المعاملة:</strong> {{ $fiskalyData['transaction_number'] }}
            </div>
            @endif

            @if(!empty($fiskalyData['tss_serial_number']))
            <div class="row">
                <strong>TSS Serial:</strong> {{ $fiskalyData['tss_serial_number'] }}
            </div>
            @endif

            @if(!empty($fiskalyData['client_serial_number']))
            <div class="row">
                <strong>Client Serial:</strong> {{ $fiskalyData['client_serial_number'] }}
            </div>
            @endif

            @if(!empty($fiskalyData['time_start']))
            <div class="row">
                <strong>وقت البدء:</strong> {{ \Carbon\Carbon::parse($fiskalyData['time_start'])->format('Y-m-d H:i:s') }}
            </div>
            @endif

            @if(!empty($fiskalyData['time_end']))
            <div class="row">
                <strong>وقت الانتهاء:</strong> {{ \Carbon\Carbon::parse($fiskalyData['time_end'])->format('Y-m-d H:i:s') }}
            </div>
            @endif

            @if(!empty($fiskalyData['signature']['counter']))
            <div class="row">
                <strong>Signature Counter:</strong> {{ $fiskalyData['signature']['counter'] }}
            </div>
            @endif

            @if(!empty($fiskalyData['signature']['algorithm']))
            <div class="row">
                <strong>Algorithm:</strong> {{ $fiskalyData['signature']['algorithm'] }}
            </div>
            @endif
        </div>

        <!-- QR Code -->
        @if(!empty($fiskalyData['qr_code_data']))
        <div class="qr-code">
            <img src="data:image/png;base64,{{ $qrCode }}" alt="QR Code">
            <p style="font-size: 8px; margin-top: 5px;">امسح للتحقق</p>
        </div>
        @endif

        <!-- Signature -->
        @if(!empty($fiskalyData['signature']['value']))
        <div class="signature">
            <strong style="font-size: 9px;">Signature:</strong><br>
            {{ $fiskalyData['signature']['value'] }}
        </div>
        @endif
    </div>
    @else
    <!-- Offline Mode -->
    <div class="fiskaly-section">
        <h3>⚠️ وضع عدم الاتصال</h3>
        <div class="info">
            <p style="text-align: center;">
                Sicherungseinrichtung ausgefallen<br>
                <small>(نظام الأمان غير متوفر)</small>
            </p>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p><strong>شكراً لزيارتكم!</strong></p>
        <p>نتطلع لخدمتكم مرة أخرى</p>
        <p style="margin-top: 10px; font-size: 9px;">
            هذا الإيصال صالح كفاتورة ضريبية
        </p>
    </div>

    <!-- Print Button (for browser view) -->
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            طباعة الإيصال
        </button>
    </div>
</body>
</html>
