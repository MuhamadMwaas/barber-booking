{{-- Appointment Print Ticket — 80mm thermal printer, B/W, multi-language --}}
@php
    $tr = fn($key, $replace = []) => __('dashboard.print_ticket.' . $key, $replace);

    $hasCustomer = (bool) $root->customer_id;
    $sourceLabel = $root->booking_source?->label() ?? '—';
    $sourceIcon  = $root->booking_source?->htmlIcon() ?? '';

    $countdownText = match ($countdown['state']) {
        'upcoming'   => $tr('countdown_starts_in', ['time' => $countdown['minutes'] >= 60
                            ? floor($countdown['minutes'] / 60) . 'h ' . ($countdown['minutes'] % 60) . 'm'
                            : $countdown['minutes'] . 'm']),
        'in_progress' => $tr('countdown_in_progress'),
        'ended'       => $tr('countdown_ended', ['time' => $countdown['minutes'] >= 60
                            ? floor($countdown['minutes'] / 60) . 'h ' . ($countdown['minutes'] % 60) . 'm'
                            : $countdown['minutes'] . 'm']),
        default       => '',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $tr('title') }} #{{ $root->number }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 2mm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        body {
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 11px;
            line-height: 1.35;
            width: 76mm;
        }

        .ticket {
            padding: 0 2mm;
        }

        .center  { text-align: center; }
        .right   { text-align: {{ $isRtl ? 'left'  : 'right' }}; }
        .left    { text-align: {{ $isRtl ? 'right' : 'left'  }}; }

        .bold    { font-weight: bold; }
        .upper   { text-transform: uppercase; letter-spacing: 0.5px; }

        .hr-double {
            border: 0;
            border-top: 1px solid #000;
            margin: 4px 0;
            position: relative;
        }
        .hr-double::after {
            content: '';
            display: block;
            border-top: 1px solid #000;
            margin-top: 2px;
        }

        .hr-single {
            border: 0;
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .company-name {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .order-no {
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 4px 0 2px;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
        }

        .kv {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            margin: 1px 0;
        }
        .kv .k { font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th, td {
            padding: 2px 1px;
            vertical-align: top;
        }

        thead th {
            border-bottom: 1px solid #000;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            font-size: 10px;
        }

        tbody tr td {
            border-bottom: 1px dashed #000;
        }

        .col-num   { width: 6%;  text-align: center; }
        .col-name  { width: 64%; }
        .col-price { width: 30%; text-align: {{ $isRtl ? 'left'  : 'right' }}; }

        .total-row {
            margin-top: 4px;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            font-size: 10px;
            font-weight: bold;
            border: 1.5px solid #000;
            border-radius: 2px;
            text-transform: uppercase;
        }

        .badge-filled {
            background: #000;
            color: #fff;
        }

        .swatch {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            vertical-align: middle;
            margin-{{ $isRtl ? 'left' : 'right' }}: 4px;
        }

        .note-box {
            border: 1px solid #000;
            padding: 4px 6px;
            margin-top: 2px;
            font-size: 11px;
            word-wrap: break-word;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 6px;
        }

        .provider-block {
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px dashed #000;
        }

        @media print {
            body { width: 80mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="ticket">

        {{-- ───────────────── HEADER: Company info ───────────────── --}}
        <div class="center">
            <div class="company-name">{{ $company['name'] }}</div>
            @if ($company['address'])
                <div>{{ $company['address'] }}</div>
            @endif
            @if ($company['phone'] || $company['email'])
                <div>
                    @if ($company['phone']) {{ $company['phone'] }} @endif
                    @if ($company['phone'] && $company['email']) | @endif
                    @if ($company['email']) {{ $company['email'] }} @endif
                </div>
            @endif
        </div>

        <hr class="hr-double">

        {{-- ───────────────── ORDER HEADER ───────────────── --}}
        <div class="center upper">{{ $tr('title') }}</div>
        <div class="center order-no">#{{ $root->number }}</div>

        @if ($appointments->count() > 1)
            <div class="center" style="font-size: 9px; margin-top: 2px;">
                {{ $tr('linked_group', ['count' => $appointments->count()]) }}
            </div>
        @endif

        <hr class="hr-single">

        {{-- ───────────────── DATE / TIME / COUNTDOWN ───────────────── --}}
        <div class="kv">
            <span class="k">{{ $tr('date') }}:</span>
            <span>{{ $root->appointment_date?->format('d M Y') }}</span>
        </div>
        <div class="kv">
            <span class="k">{{ $tr('time') }}:</span>
            <span>
                {{ $root->start_time?->format('H:i') }} - {{ $root->end_time?->format('H:i') }}
                ({{ $root->duration_minutes }}m)
            </span>
        </div>

        @if ($countdownText)
            <div class="kv">
                <span class="k">{{ $tr('status_now') }}:</span>
                <span class="bold">{{ $countdownText }}</span>
            </div>
        @endif

        <hr class="hr-single">

        {{-- ───────────────── CUSTOMER ───────────────── --}}
        <div class="section-title">{{ $tr('section_customer') }}</div>
        <div class="kv">
            <span class="k">{{ $tr('name') }}:</span>
            <span>{{ $root->customer_name }}</span>
        </div>
        @if ($root->customer_phone)
            <div class="kv">
                <span class="k">{{ $tr('phone') }}:</span>
                <span>{{ $root->customer_phone }}</span>
            </div>
        @endif
        <div class="kv">
            <span class="k">{{ $tr('account') }}:</span>
            <span>{{ $hasCustomer ? $tr('account_registered') : $tr('account_guest') }}</span>
        </div>

        {{-- ───────────────── SOURCE ───────────────── --}}
        <div class="kv" style="margin-top: 4px;">
            <span class="k">{{ $tr('source') }}:</span>
            <span>{{ $sourceIcon }} {{ $sourceLabel }}</span>
        </div>

        {{-- ───────────────── SERVICES — per appointment ───────────────── --}}
        @foreach ($appointments as $apt)
            <div class="provider-block">
                <div class="kv">
                    <span class="k">{{ $tr('provider') }}:</span>
                    <span>{{ $apt->provider?->full_name ?? $apt->provider?->name ?? '—' }}</span>
                </div>

                @if ($appointments->count() > 1)
                    <div class="kv" style="font-size: 9px;">
                        <span class="k">{{ $tr('booking_no') }}:</span>
                        <span>#{{ $apt->number }}</span>
                    </div>
                @endif
            </div>

            <div class="section-title">{{ $tr('section_services') }}</div>
            <table>
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-name">{{ $tr('service') }}</th>
                        <th class="col-price">{{ $tr('price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($apt->services_record as $i => $s)
                        <tr>
                            <td class="col-num">{{ $i + 1 }}</td>
                            <td class="col-name">
                                {{ $s->service_name }}
                                <div style="font-size: 9px;">{{ $s->duration_minutes }}m</div>
                            </td>
                            <td class="col-price">{{ number_format((float) $s->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        {{-- ───────────────── GRAND TOTAL ───────────────── --}}
        <div class="total-row">
            <span>{{ $tr('total') }}</span>
            <span>{{ number_format($grandTotal, 2) }} EUR</span>
        </div>

        <div class="center" style="margin-top: 4px;">
            <span class="badge {{ $isPaid ? 'badge-filled' : '' }}">
                {{ $isPaid ? $tr('paid') : $tr('unpaid') }}
            </span>
        </div>

        {{-- ───────────────── COLORS — aggregated across the group ───────────────── --}}
        @php
            $allColors = $appointments
                ->flatMap(fn ($apt) => $apt->colorRecords ?? collect())
                ->filter(fn ($cr) => $cr->color !== null);
        @endphp

        @if ($allColors->isNotEmpty())
            <hr class="hr-single">
            <div class="section-title">{{ $tr('section_colors') }}</div>
            <table>
                <tbody>
                    @foreach ($allColors as $cr)
                        <tr>
                            <td style="width: 16px;">
                                <span class="swatch" style="background: {{ $cr->color->hex_code }};"></span>
                            </td>
                            <td>
                                {{ $cr->color->name }}
                                @if ($cr->color->brand)
                                    <span style="font-size: 9px;">({{ $cr->color->brand }})</span>
                                @endif
                            </td>
                            <td class="right">
                                {{ rtrim(rtrim(number_format((float) $cr->quantity, 2), '0'), '.') }}
                                {{ $cr->color->unit }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- ───────────────── CUSTOMER NOTES (per appointment) ───────────────── --}}
        @php
            $customerNotes = $appointments
                ->map(fn ($apt) => trim((string) $apt->notes))
                ->filter()
                ->unique()
                ->values();
        @endphp

        @if ($customerNotes->isNotEmpty())
            <hr class="hr-single">
            <div class="section-title">{{ $tr('section_notes') }}</div>
            @foreach ($customerNotes as $note)
                <div class="note-box">{{ $note }}</div>
            @endforeach
        @endif

        {{-- ───────────────── PROVIDER NOTES (per appointment) ───────────────── --}}
        @php
            $providerNotes = $appointments
                ->map(fn ($apt) => [
                    'name' => $apt->provider?->full_name ?? $apt->provider?->name ?? '—',
                    'note' => trim((string) $apt->provider_notes),
                ])
                ->filter(fn ($e) => $e['note'] !== '')
                ->values();
        @endphp

        @if ($providerNotes->isNotEmpty())
            <hr class="hr-single">
            <div class="section-title">{{ $tr('section_provider_notes') }}</div>
            @foreach ($providerNotes as $pn)
                <div class="note-box">
                    @if ($appointments->count() > 1)
                        <div class="bold" style="font-size: 9px;">{{ $pn['name'] }}:</div>
                    @endif
                    {{ $pn['note'] }}
                </div>
            @endforeach
        @endif

        {{-- ───────────────── FOOTER ───────────────── --}}
        <hr class="hr-double">
        <div class="footer">
            {{ $tr('printed_at') }}: {{ $printedAt->format('Y-m-d H:i') }}
        </div>
        <div class="footer bold">{{ $tr('thank_you') }}</div>

    </div>

    <script>
        (function () {
            let printed = false;
            function go() {
                if (printed) return;
                printed = true;
                setTimeout(() => window.print(), 300);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', go);
            } else {
                go();
            }
        })();
    </script>
</body>
</html>
