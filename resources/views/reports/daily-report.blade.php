{{--
    Daily Report — Z-Report & Employee Summary (printable A4 document).

    Standalone on purpose: it ships its own <html> shell and CSS rather than the
    dashboard layout, because it is opened in its own tab and printed. All
    numbers arrive pre-computed from DailyReportService — this file contains
    presentation only (formatting, layout, colours).
--}}
@php
    $tr  = fn ($key, $replace = []) => __('z_report.' . $key, $replace);

    $meta       = $report['meta'];
    $company    = $report['company'];
    $sales      = $report['sales'];
    $totals     = $report['totals'];
    $vat        = $report['vat'];
    $discounts  = $report['discounts'];
    $operations = $report['operations'];
    $services   = $report['services'];
    $employees  = $report['employees'];
    $daily      = $report['daily'];
    $txns       = $report['transactions'];

    $currency = $meta['currency'] ?: '€';

    // ── Formatting helpers ────────────────────────────────────────────────
    $money = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
    $pct   = fn ($value) => number_format((float) $value, 1) . '%';

    $hours = function (int $minutes) {
        if ($minutes <= 0) {
            return '—';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? ($m ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    };

    $shortDate = fn ($date) => \Carbon\Carbon::parse($date)->format('d/m/Y');

    // Initials for the employee avatar chips.
    $initials = function (string $name) {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($first . $second) ?: '—';
    };

    // Deterministic avatar colour per provider, so the same person keeps the
    // same chip colour across every printed report.
    $avatarPalette = ['#c2410c', '#0f766e', '#4338ca', '#a16207', '#9d174d', '#15803d', '#1d4ed8', '#7c2d12'];
    $avatarColor = fn ($id) => $avatarPalette[((int) $id) % count($avatarPalette)];

    // ── Donut geometry ────────────────────────────────────────────────────
    // Pure SVG, no chart library: each slice is an arc drawn with
    // stroke-dasharray on a shared circle, offset by the running total.
    $donutRadius = 54;
    $donutCircumference = 2 * M_PI * $donutRadius;
    $methodColors = ['cash' => '#10b981', 'card' => '#3b82f6', 'online' => '#8b5cf6'];

    $donutSlices = [];
    $donutOffset = 0.0;
    foreach ($sales['buckets'] as $method => $bucket) {
        if ($bucket['amount'] <= 0) {
            continue;
        }
        $fraction = $sales['total_amount'] > 0 ? $bucket['amount'] / $sales['total_amount'] : 0;
        $donutSlices[] = [
            'method' => $method,
            'color'  => $methodColors[$method],
            'length' => $fraction * $donutCircumference,
            'offset' => -$donutOffset,
        ];
        $donutOffset += $fraction * $donutCircumference;
    }

    $hasSales = $sales['total_amount'] > 0 || $sales['total_count'] > 0;
    $maxServiceRevenue = collect($services)->max('revenue') ?: 1;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tr('title') }} — {{ $meta['report_id'] }}</title>
    <style>
        /* ── Page setup ──────────────────────────────────────────────── */
        @page {
            size: A4;
            margin: 10mm 8mm;
        }

        * { box-sizing: border-box; }

        :root {
            --amber:   #d97706;
            --amber-l: #fef3c7;
            --amber-b: #fde68a;
            --green:   #059669;
            --green-l: #d1fae5;
            --red:     #dc2626;
            --blue:    #2563eb;
            --violet:  #7c3aed;
            --ink:     #1f2937;
            --muted:   #6b7280;
            --faint:   #9ca3af;
            --line:    #e5e7eb;
            --panel:   #f9fafb;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #eef1f5;
            color: var(--ink);
        }

        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, 'Noto Sans Arabic', sans-serif;
            font-size: 10px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 10mm 9mm;
            background: #fff;
            box-shadow: 0 2px 18px rgba(0, 0, 0, .12);
        }

        /* ── Toolbar (screen only) ───────────────────────────────────── */
        .toolbar {
            width: 210mm;
            margin: 14px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            font: inherit;
            font-weight: 600;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            padding: 7px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--amber);
            border-color: var(--amber);
            color: #fff;
        }

        .btn:hover { filter: brightness(.96); }

        /* ── Document header ─────────────────────────────────────────── */
        .doc-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--ink);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            flex: 1;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--amber);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: -.5px;
            flex-shrink: 0;
        }

        .brand-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--amber);
            text-transform: uppercase;
            letter-spacing: .4px;
            line-height: 1.15;
        }

        .brand-sub {
            font-size: 8.5px;
            color: var(--faint);
        }

        .doc-title {
            text-align: center;
            flex: 1.4;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .doc-title p {
            margin: 2px 0 0;
            font-size: 9px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 1.6px;
            text-transform: uppercase;
        }

        .doc-meta {
            flex: 1;
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 6px 9px;
            font-size: 8.5px;
            min-width: 130px;
        }

        .doc-meta div {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .doc-meta span:first-child { color: var(--faint); }
        .doc-meta span:last-child  { font-weight: 700; }

        /* ── Notices ─────────────────────────────────────────────────── */
        .notice {
            margin-top: 8px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 600;
            background: var(--amber-l);
            border: 1px solid var(--amber-b);
            color: #92400e;
        }

        /* ── Layout ──────────────────────────────────────────────────── */
        .cols {
            display: flex;
            gap: 9px;
            margin-top: 9px;
            align-items: flex-start;
        }

        .col-left  { width: 39%; }
        .col-right { width: 61%; }

        /* The employee summary carries seven columns of money in the narrower
           half of the page, so it gets a tighter scale of its own. Without this
           the last column is pushed off the sheet. */
        .table-tight { font-size: 8px; table-layout: fixed; }
        .table-tight th,
        .table-tight td { padding: 4px 3px; }
        /* Headers wrap here instead of running into each other: seven columns
           of money leave no room for a one-line "Average ticket". */
        .table-tight thead th {
            font-size: 7px;
            letter-spacing: 0;
            white-space: normal;
            line-height: 1.2;
        }

        .table-tight td { white-space: nowrap; }

        .table-tight td:first-child,
        .table-tight th:first-child { width: 27%; }

        .table-tight .who span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 9px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .card-head {
            background: var(--amber-l);
            color: #92400e;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: center;
            padding: 5px 8px;
            border-bottom: 1px solid var(--amber-b);
        }

        .card-body { padding: 7px 10px; }
        .card-body.flush { padding: 0; }

        /* ── Key/value rows ──────────────────────────────────────────── */
        .kv {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 3.5px 0;
            border-bottom: 1px dotted #eceff3;
        }

        .kv:last-child { border-bottom: 0; }
        .kv dt { color: var(--muted); }
        .kv dd { margin: 0; font-weight: 700; text-align: end; white-space: nowrap; }

        .kv.strong {
            border-top: 1px solid var(--line);
            border-bottom: 0;
            margin-top: 3px;
            padding-top: 6px;
            font-size: 11px;
        }

        .kv.strong dt { color: var(--ink); font-weight: 700; }

        .num-amber  { color: var(--amber); }
        .num-green  { color: var(--green); }
        .num-red    { color: var(--red); }
        .num-blue   { color: var(--blue); }
        .num-violet { color: var(--violet); }
        .num-muted  { color: var(--faint); font-weight: 600; }

        /* ── Tables ──────────────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        thead th {
            background: var(--panel);
            color: var(--faint);
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 5px 6px;
            border-bottom: 1px solid var(--line);
            text-align: center;
            white-space: nowrap;
        }

        thead th:first-child { text-align: start; }
        thead th:last-child  { text-align: end; }

        tbody td {
            padding: 5px 6px;
            border-bottom: 1px solid #f3f4f6;
            text-align: center;
            white-space: nowrap;
        }

        tbody td:first-child { text-align: start; white-space: normal; }
        tbody td:last-child  { text-align: end; font-weight: 700; }

        tbody tr:last-child td { border-bottom: 0; }

        tfoot td {
            padding: 6px;
            border-top: 1.5px solid var(--ink);
            font-weight: 800;
            text-align: center;
            white-space: nowrap;
        }

        tfoot td:first-child { text-align: start; }
        tfoot td:last-child  { text-align: end; }

        /* ── Employee chips ──────────────────────────────────────────── */
        .who {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .avatar {
            width: 19px;
            height: 19px;
            border-radius: 50%;
            color: #fff;
            font-size: 7.5px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            letter-spacing: -.2px;
        }

        .avatar.lg { width: 24px; height: 24px; font-size: 9px; }

        /* ── Employee detail cards ───────────────────────────────────── */
        .emp {
            border: 1px solid var(--line);
            border-radius: 7px;
            margin-bottom: 7px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .emp-head {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 5px 9px;
            background: var(--panel);
            border-bottom: 1px solid var(--line);
        }

        .emp-name { font-size: 10.5px; font-weight: 800; }

        .emp-grid {
            display: flex;
            gap: 12px;
            padding: 7px 10px;
        }

        .emp-grid > div { flex: 1; min-width: 0; }

        .emp-total {
            border-top: 1px solid var(--line);
            padding-top: 5px;
            margin-top: 3px;
        }

        /* ── Bars ────────────────────────────────────────────────────── */
        .bar {
            height: 4px;
            border-radius: 2px;
            background: #f1f3f6;
            overflow: hidden;
            margin-top: 3px;
        }

        .bar > span {
            display: block;
            height: 100%;
            border-radius: 2px;
            background: var(--amber);
        }

        .split-bar {
            display: flex;
            height: 7px;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0 3px;
            background: #f1f3f6;
        }

        /* ── Donut ───────────────────────────────────────────────────── */
        .donut-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .donut { flex-shrink: 0; }

        /* min-width:0 lets the legend shrink inside the flex row instead of
           forcing its widest amount to overflow the card. */
        .legend { flex: 1; min-width: 0; }

        .legend-item {
            display: flex;
            align-items: baseline;
            gap: 5px;
            padding: 2.5px 0;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-label {
            flex: 1;
            min-width: 0;
            color: var(--muted);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .legend-value { font-weight: 700; white-space: nowrap; font-size: 9px; }
        .legend-pct   { color: var(--faint); white-space: nowrap; font-size: 8px; }

        /* ── Footer ──────────────────────────────────────────────────── */
        .foot {
            display: flex;
            gap: 9px;
            margin-top: 4px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .foot > div {
            flex: 1;
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 7px 10px;
        }

        .foot-label {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--faint);
            margin-bottom: 3px;
        }

        .stamp {
            margin-top: 9px;
            padding: 7px 12px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: var(--panel);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 8.5px;
            color: var(--muted);
            break-inside: avoid;
        }

        .stamp b { color: var(--ink); font-size: 9.5px; letter-spacing: .4px; }

        /* Bidi isolation for date/time RANGES.
           In RTL, "12/08/2026 – 15/08/2026" is reordered by the bidi algorithm
           and reads as "15/08/2026 – 12/08/2026" — the period looks inverted.
           Same for "09:07 – 17:22". Isolating the run keeps from→to order. */
        .ltr {
            direction: ltr;
            unicode-bidi: isolate;
            display: inline-block;
        }

        .empty {
            text-align: center;
            color: var(--faint);
            padding: 14px 8px;
            font-style: italic;
        }

        .section-note {
            font-size: 8px;
            color: var(--faint);
            padding: 4px 10px 7px;
        }

        /* ── Print ───────────────────────────────────────────────────── */
        @media print {
            body   { background: #fff; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .toolbar { display: none !important; }
            .card, .emp, .foot, .stamp { break-inside: avoid; page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" class="btn btn-primary" onclick="window.print()">{{ $tr('print') }}</button>
    <button type="button" class="btn" onclick="window.close()">{{ $tr('close') }}</button>
</div>

<div class="sheet">

    {{-- ══════════════════════════ HEADER ══════════════════════════ --}}
    <div class="doc-head">
        <div class="brand">
            <div class="brand-mark">{{ mb_strtoupper(mb_substr($company['name'] ?: 'S', 0, 2)) }}</div>
            <div>
                <div class="brand-name">{{ $company['name'] }}</div>
                @if ($company['tax_number'])
                    <div class="brand-sub">{{ $company['tax_number'] }}</div>
                @elseif ($company['phone'])
                    <div class="brand-sub">{{ $company['phone'] }}</div>
                @endif
            </div>
        </div>

        <div class="doc-title">
            <h1>{{ $tr('title') }}</h1>
            <p>{{ $tr('subtitle') }}</p>
        </div>

        <div class="doc-meta">
            <div>
                <span>{{ $meta['is_single_day'] ? $tr('date_label') : $tr('range_label') }}:</span>
                <span class="ltr">
                    @if ($meta['is_single_day'])
                        {{ $shortDate($meta['from']) }}
                    @else
                        {{ $shortDate($meta['from']) }} – {{ $shortDate($meta['to']) }}
                    @endif
                </span>
            </div>
            <div>
                <span>{{ $tr('printed') }}:</span>
                <span class="ltr">{{ $meta['generated_at']->format('d/m/Y H:i') }}</span>
            </div>
            <div>
                <span>{{ $tr('generated_by') }}:</span>
                <span>{{ $meta['generated_by'] }}</span>
            </div>
        </div>
    </div>

    @if ($meta['is_filtered'])
        <div class="notice">{{ $tr('filtered_notice') }}</div>
    @endif

    @if (! $hasSales)
        <div class="notice">{{ $tr('empty_report') }}</div>
    @endif

    {{-- ══════════════════════════ BODY ════════════════════════════ --}}
    <div class="cols">

        {{-- ─────────────────── LEFT COLUMN ─────────────────── --}}
        <div class="col-left">

            {{-- 1. Sales summary --}}
            <div class="card">
                <div class="card-head">1 · {{ $tr('sales_title') }}</div>
                <div class="card-body flush">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ $tr('method') }}</th>
                                <th>{{ $tr('transactions') }}</th>
                                <th>{{ $tr('amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sales['buckets'] as $method => $bucket)
                                {{-- Online is hidden when unused, so a salon that
                                     never takes online payments gets a clean report. --}}
                                @continue($method === 'online' && $bucket['count'] === 0)
                                <tr>
                                    <td>
                                        <span class="dot" style="display:inline-block;background:{{ $methodColors[$method] }}"></span>
                                        {{ $tr($method) }}
                                    </td>
                                    <td>{{ $bucket['count'] }}</td>
                                    <td>{{ $money($bucket['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>{{ $tr('total_sales') }}</td>
                                <td>{{ $sales['total_count'] }}</td>
                                <td class="num-green">{{ $money($sales['total_amount']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- 2. Payment split (donut) --}}
            <div class="card">
                <div class="card-head">2 · {{ $tr('payment_title') }}</div>
                <div class="card-body">
                    <div class="donut-row">
                        <svg class="donut" width="128" height="128" viewBox="0 0 128 128">
                            <circle cx="64" cy="64" r="{{ $donutRadius }}"
                                    fill="none" stroke="#f1f3f6" stroke-width="17"/>
                            @foreach ($donutSlices as $slice)
                                <circle cx="64" cy="64" r="{{ $donutRadius }}"
                                        fill="none"
                                        stroke="{{ $slice['color'] }}"
                                        stroke-width="17"
                                        stroke-dasharray="{{ round($slice['length'], 2) }} {{ round($donutCircumference, 2) }}"
                                        stroke-dashoffset="{{ round($slice['offset'], 2) }}"
                                        transform="rotate(-90 64 64)"/>
                            @endforeach
                            <text x="64" y="61" text-anchor="middle"
                                  font-size="13" font-weight="800" fill="#1f2937">
                                {{ $sales['total_count'] }}
                            </text>
                            <text x="64" y="74" text-anchor="middle"
                                  font-size="7.5" fill="#9ca3af" letter-spacing=".5">
                                {{ mb_strtoupper($tr('transactions')) }}
                            </text>
                        </svg>

                        <div class="legend">
                            @foreach ($sales['buckets'] as $method => $bucket)
                                @continue($method === 'online' && $bucket['count'] === 0)
                                <div class="legend-item">
                                    <span class="dot" style="background:{{ $methodColors[$method] }}"></span>
                                    <span class="legend-label">{{ $tr($method) }}</span>
                                    <span class="legend-value">{{ $money($bucket['amount']) }}</span>
                                    <span class="legend-pct">{{ $pct($bucket['percent']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Full width rather than inside the legend, so a long
                         amount can never overflow the narrow legend column. --}}
                    <div class="kv strong">
                        <dt>{{ $tr('total_sales') }}</dt>
                        <dd class="num-green">{{ $money($sales['total_amount']) }}</dd>
                    </div>
                </div>
            </div>

            {{-- 3. Report totals --}}
            <div class="card">
                <div class="card-head">3 · {{ $tr('totals_title') }}</div>
                <div class="card-body">
                    <dl style="margin:0">
                        <div class="kv"><dt>{{ $tr('total_transactions') }}</dt><dd>{{ $totals['transactions'] }}</dd></div>
                        <div class="kv"><dt>{{ $tr('total_receipts') }}</dt><dd>{{ $totals['receipts'] }}</dd></div>
                        <div class="kv"><dt>{{ $tr('total_customers') }}</dt><dd>{{ $totals['customers'] }}</dd></div>
                        <div class="kv"><dt>{{ $tr('total_services') }}</dt><dd>{{ $totals['services_sold'] }}</dd></div>
                        <div class="kv strong"><dt>{{ $tr('avg_ticket') }}</dt><dd class="num-amber">{{ $money($totals['avg_ticket']) }}</dd></div>
                    </dl>
                </div>
            </div>

            {{-- 4. VAT --}}
            <div class="card">
                <div class="card-head">4 · {{ $tr('vat_title') }}</div>
                <div class="card-body flush">
                    @if (empty($vat['rows']))
                        <p class="empty">—</p>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ $tr('vat_rate') }}</th>
                                    <th>{{ $tr('vat_net') }}</th>
                                    <th>{{ $tr('vat_tax') }}</th>
                                    <th>{{ $tr('vat_gross') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vat['rows'] as $row)
                                    <tr>
                                        <td>{{ number_format($row['rate'], 0) }}%</td>
                                        <td>{{ $money($row['net']) }}</td>
                                        <td class="num-amber">{{ $money($row['tax']) }}</td>
                                        <td>{{ $money($row['gross']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>{{ $tr('total_row') }}</td>
                                    <td>{{ $money($vat['net']) }}</td>
                                    <td class="num-amber">{{ $money($vat['tax']) }}</td>
                                    <td class="num-green">{{ $money($vat['gross']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                    <p class="section-note">{{ $tr('vat_note') }}</p>
                </div>
            </div>

            {{-- 5. Discounts --}}
            <div class="card">
                <div class="card-head">5 · {{ $tr('discount_title') }}</div>
                <div class="card-body">
                    <dl style="margin:0">
                        <div class="kv"><dt>{{ $tr('discount_before') }}</dt><dd>{{ $money($discounts['before']) }}</dd></div>
                        <div class="kv">
                            <dt>{{ $tr('discount_total') }}</dt>
                            <dd class="{{ $discounts['total'] > 0 ? 'num-red' : 'num-muted' }}">
                                {{ $discounts['total'] > 0 ? '- ' : '' }}{{ $money($discounts['total']) }}
                            </dd>
                        </div>
                        <div class="kv"><dt>{{ $tr('discount_count') }}</dt><dd>{{ $discounts['count'] }}</dd></div>
                        <div class="kv"><dt>{{ $tr('discount_percent') }}</dt><dd class="num-muted">{{ $pct($discounts['percent']) }}</dd></div>
                        <div class="kv strong"><dt>{{ $tr('total_sales') }}</dt><dd class="num-green">{{ $money($sales['total_amount']) }}</dd></div>
                    </dl>
                </div>
            </div>

            {{-- 6. Operations --}}
            <div class="card">
                <div class="card-head">6 · {{ $tr('operations_title') }}</div>
                <div class="card-body">
                    @php
                        $sourceTotal = max(1, $operations['source_online'] + $operations['source_in_person']);
                        $onlinePct = round($operations['source_online'] / $sourceTotal * 100);
                    @endphp

                    <div style="font-size:8.5px;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.5px">
                        {{ $tr('source_title') }}
                    </div>
                    <div class="split-bar">
                        <span style="width:{{ $onlinePct }}%;background:var(--violet)"></span>
                        <span style="width:{{ 100 - $onlinePct }}%;background:#14b8a6"></span>
                    </div>
                    <div class="kv" style="border-bottom:0;padding-top:0">
                        <dt><span class="dot" style="display:inline-block;background:var(--violet)"></span> {{ $tr('source_app') }}</dt>
                        <dd class="num-violet">{{ $operations['source_online'] }}</dd>
                    </div>
                    <div class="kv">
                        <dt><span class="dot" style="display:inline-block;background:#14b8a6"></span> {{ $tr('source_reception') }}</dt>
                        <dd style="color:#0f766e">{{ $operations['source_in_person'] }}</dd>
                    </div>

                    <dl style="margin:6px 0 0">
                        <div class="kv">
                            <dt>{{ $tr('cancelled') }}</dt>
                            <dd class="{{ $operations['cancelled'] > 0 ? 'num-red' : 'num-muted' }}">{{ $operations['cancelled'] }}</dd>
                        </div>
                        <div class="kv">
                            <dt>{{ $tr('no_show') }}</dt>
                            <dd class="{{ $operations['no_show'] > 0 ? 'num-amber' : 'num-muted' }}">{{ $operations['no_show'] }}</dd>
                        </div>
                        <div class="kv">
                            <dt>{{ $tr('outstanding_count') }}</dt>
                            <dd class="num-muted">{{ $operations['outstanding_count'] }}</dd>
                        </div>
                        <div class="kv strong">
                            <dt>{{ $tr('outstanding') }}</dt>
                            <dd class="{{ $operations['outstanding_amount'] > 0 ? 'num-amber' : 'num-muted' }}">
                                {{ $money($operations['outstanding_amount']) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="section-note" style="padding:5px 0 0">{{ $tr('scheduled_note') }}</p>
                </div>
            </div>

            {{-- 7. Services breakdown --}}
            <div class="card">
                <div class="card-head">7 · {{ $tr('services_title') }}</div>
                <div class="card-body flush">
                    @if (empty($services))
                        <p class="empty">{{ $tr('services_empty') }}</p>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ $tr('service') }}</th>
                                    <th>{{ $tr('qty') }}</th>
                                    <th>{{ $tr('share') }}</th>
                                    <th>{{ $tr('revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($services as $service)
                                    <tr>
                                        <td>
                                            {{ $service['name'] }}
                                            <div class="bar">
                                                <span style="width:{{ round($service['revenue'] / $maxServiceRevenue * 100) }}%"></span>
                                            </div>
                                        </td>
                                        <td>{{ $service['count'] }}</td>
                                        <td class="num-muted">{{ $pct($service['percent']) }}</td>
                                        <td>{{ $money($service['revenue']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- ─────────────────── RIGHT COLUMN ─────────────────── --}}
        <div class="col-right">

            {{-- Employee summary --}}
            <div class="card">
                <div class="card-head">{{ $tr('employee_summary') }}</div>
                <div class="card-body flush">
                    @if (empty($employees['rows']))
                        <p class="empty">{{ $tr('employees_empty') }}</p>
                    @else
                        <table class="table-tight">
                            <thead>
                                <tr>
                                    <th>{{ $tr('employee') }}</th>
                                    <th>{{ $tr('appointments') }}</th>
                                    <th>{{ $tr('services') }}</th>
                                    <th>{{ $tr('cash_sales') }}</th>
                                    <th>{{ $tr('card_sales') }}</th>
                                    <th>{{ $tr('avg_ticket') }}</th>
                                    <th>{{ $tr('total_revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees['rows'] as $row)
                                    <tr>
                                        <td>
                                            <div class="who">
                                                <span class="avatar" style="background:{{ $avatarColor($row['provider_id']) }}">
                                                    {{ $initials($row['provider_name']) }}
                                                </span>
                                                <span>{{ $row['provider_name'] }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $row['appointments'] }}</td>
                                        <td>{{ $row['services'] }}</td>
                                        <td class="num-green">{{ $money($row['cash']) }}</td>
                                        <td class="num-blue">{{ $money($row['card']) }}</td>
                                        <td class="num-muted">{{ $money($row['avg_ticket']) }}</td>
                                        <td class="num-green">{{ $money($row['total']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>{{ $tr('total_row') }}</td>
                                    <td>{{ $employees['totals']['appointments'] }}</td>
                                    <td>{{ $employees['totals']['services'] }}</td>
                                    <td class="num-green">{{ $money($employees['totals']['cash']) }}</td>
                                    <td class="num-blue">{{ $money($employees['totals']['card']) }}</td>
                                    <td class="num-amber">{{ $money($employees['totals']['avg_ticket']) }}</td>
                                    <td class="num-green">{{ $money($employees['totals']['total']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Employee details — only for staff who actually worked. Idle
                 staff are already visible (with zeros) in the summary table
                 above, so a detail card full of dashes would be pure noise. --}}
            @php
                $activeEmployees = array_values(array_filter(
                    $employees['rows'],
                    fn ($row) => $row['appointments'] > 0,
                ));
            @endphp
            @if (! empty($activeEmployees))
                <div class="card">
                    <div class="card-head">{{ $tr('employee_details') }}</div>
                    <div class="card-body">
                        @foreach ($activeEmployees as $row)
                            @php $att = $row['attendance']; @endphp
                            <div class="emp">
                                <div class="emp-head">
                                    <span class="avatar lg" style="background:{{ $avatarColor($row['provider_id']) }}">
                                        {{ $initials($row['provider_name']) }}
                                    </span>
                                    <span class="emp-name">{{ $row['provider_name'] }}</span>
                                </div>

                                <div class="emp-grid">
                                    <div>
                                        <div class="kv"><dt>{{ $tr('appointments') }}</dt><dd>{{ $row['appointments'] }}</dd></div>
                                        <div class="kv"><dt>{{ $tr('services') }}</dt><dd>{{ $row['services'] }}</dd></div>
                                        <div class="kv"><dt>{{ $tr('customers') }}</dt><dd>{{ $row['customers'] }}</dd></div>
                                        <div class="kv"><dt>{{ $tr('avg_ticket') }}</dt><dd>{{ $money($row['avg_ticket']) }}</dd></div>
                                        <div class="kv">
                                            <dt>{{ $tr('working_time') }}</dt>
                                            <dd class="{{ $att && $att['first'] ? '' : 'num-muted' }}">
                                                @if ($att && $att['first'])
                                                    <span class="ltr">{{ $att['first'] }} – {{ $att['last'] ?? '…' }}</span>
                                                @else
                                                    {{ $tr('no_attendance') }}
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="kv">
                                            <dt>{{ $tr('worked') }}</dt>
                                            <dd class="num-muted">
                                                {{ $att ? $hours($att['minutes']) : '—' }}
                                                @if ($att && $att['open_sessions'] > 0)
                                                    <span class="num-amber">· {{ $tr('still_clocked_in') }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="kv"><dt>{{ $tr('cash_sales') }}</dt><dd class="num-green">{{ $money($row['cash']) }}</dd></div>
                                        <div class="kv"><dt>{{ $tr('card_sales') }}</dt><dd class="num-blue">{{ $money($row['card']) }}</dd></div>
                                        @if ($row['online'] > 0)
                                            <div class="kv"><dt>{{ $tr('online_sales') }}</dt><dd class="num-violet">{{ $money($row['online']) }}</dd></div>
                                        @endif
                                        <div class="kv">
                                            <dt>{{ $tr('top_service') }}</dt>
                                            <dd class="{{ $row['top_service'] ? '' : 'num-muted' }}">{{ $row['top_service'] ?? '—' }}</dd>
                                        </div>
                                        <div class="kv strong emp-total">
                                            <dt>{{ $tr('total_revenue') }}</dt>
                                            <dd class="num-green">{{ $money($row['total']) }}</dd>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Daily performance — a range only; for one day it would just
                 repeat the sales summary above. --}}
            @if (! $meta['is_single_day'])
                <div class="card">
                    <div class="card-head">{{ $tr('daily_title') }}</div>
                    <div class="card-body flush">
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ $tr('day') }}</th>
                                    <th>{{ $tr('transactions') }}</th>
                                    <th>{{ $tr('cash') }}</th>
                                    <th>{{ $tr('card') }}</th>
                                    <th>{{ $tr('total_sales') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($daily as $day)
                                    <tr>
                                        <td>
                                            {{ $shortDate($day['date']) }}
                                            <span class="num-muted" style="font-weight:600">
                                                {{ \Carbon\Carbon::parse($day['date'])->locale($locale)->isoFormat('ddd') }}
                                            </span>
                                        </td>
                                        <td class="{{ $day['count'] === 0 ? 'num-muted' : '' }}">{{ $day['count'] }}</td>
                                        <td>{{ $money($day['cash']) }}</td>
                                        <td>{{ $money($day['card']) }}</td>
                                        <td class="{{ $day['total'] > 0 ? 'num-green' : 'num-muted' }}">{{ $money($day['total']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>{{ $tr('total_row') }}</td>
                                    <td>{{ $sales['total_count'] }}</td>
                                    <td>{{ $money($sales['buckets']['cash']['amount']) }}</td>
                                    <td>{{ $money($sales['buckets']['card']['amount']) }}</td>
                                    <td class="num-green">{{ $money($sales['total_amount']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════ TRANSACTION APPENDIX ══════════════════════ --}}
    <div class="card">
        <div class="card-head">{{ $tr('appendix_title') }}</div>
        <div class="card-body flush">
            @if (empty($txns))
                <p class="empty">{{ $tr('appendix_empty') }}</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>{{ $tr('invoice_no') }}</th>
                            @if (! $meta['is_single_day'])
                                <th>{{ $tr('day') }}</th>
                            @endif
                            <th>{{ $tr('time') }}</th>
                            <th>{{ $tr('customer') }}</th>
                            <th>{{ $tr('provider') }}</th>
                            <th>{{ $tr('method') }}</th>
                            <th>{{ $tr('amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($txns as $txn)
                            <tr>
                                <td style="font-family:'Courier New',monospace">{{ $txn['invoice_number'] }}</td>
                                @if (! $meta['is_single_day'])
                                    <td>{{ $shortDate($txn['date']) }}</td>
                                @endif
                                <td>{{ $txn['time'] }}</td>
                                <td style="text-align:center">{{ $txn['customer'] }}</td>
                                <td>{{ $txn['provider'] }}</td>
                                <td>
                                    <span class="dot" style="display:inline-block;background:{{ $methodColors[$txn['method']] }}"></span>
                                    {{ $tr($txn['method']) }}
                                </td>
                                <td>{{ $money($txn['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="{{ $meta['is_single_day'] ? 5 : 6 }}">{{ $tr('total_row') }}</td>
                            <td class="num-green">{{ $money($sales['total_amount']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════ FOOTER ══════════════════════════ --}}
    <div class="foot">
        <div>
            <div class="foot-label">{{ $company['name'] }}</div>
            @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
            @if ($company['phone'])<div>{{ $company['phone'] }}</div>@endif
            @if ($company['email'])<div>{{ $company['email'] }}</div>@endif
            @if ($company['tax_number'])<div>{{ $company['tax_number'] }}</div>@endif
            {{-- A salon that has not filled in its details would otherwise get a
                 blank box on every printed report. --}}
            @if (! $company['address'] && ! $company['phone'] && ! $company['email'] && ! $company['tax_number'])
                <div style="color:var(--faint)">
                    @if ($meta['is_single_day'])
                        {{ \Carbon\Carbon::parse($meta['from'])->locale($locale)->isoFormat('dddd, D MMMM YYYY') }}
                    @else
                        <span class="ltr">{{ $shortDate($meta['from']) }} – {{ $shortDate($meta['to']) }}</span>
                    @endif
                </div>
            @endif
        </div>
        <div>
            <div class="foot-label">{{ $tr('report_id') }}</div>
            <div style="font-family:'Courier New',monospace;font-weight:700;font-size:10px">{{ $meta['report_id'] }}</div>
            <div style="color:var(--faint);margin-top:2px">{{ $tr('footer_note') }}</div>
        </div>
    </div>

    <div class="stamp">
        <span><b class="ltr">{{ $meta['report_id'] }}</b></span>
        <span>
            {{ $tr('printed') }}: <span class="ltr">{{ $meta['generated_at']->format('d/m/Y H:i:s') }}</span>
            · {{ $tr('generated_by') }}: {{ $meta['generated_by'] }}
        </span>
    </div>
</div>

</body>
</html>
