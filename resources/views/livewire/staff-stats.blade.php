@php
    // ── Presentation-only helpers (no business logic; numbers come pre-computed
    //    from DashboardStatsService via the component) ──────────────────────────
    $rtl = app()->getLocale() === 'ar';
    $fmtMoney = fn ($v) => number_format((float) $v, 2) . ' €';
    $fmtHours = function (int $m) {
        $h = intdiv($m, 60);
        $min = $m % 60;
        if ($h > 0) {
            return $min ? ($h . 'h ' . $min . 'm') : ($h . 'h');
        }
        return $min . 'm';
    };

    // Accent class lookups written as full literal strings so Tailwind never
    // purges them (dynamic `bg-{$x}-100` would be stripped at build time).
    $accents = [
        'emerald' => ['chip' => 'bg-emerald-100 text-emerald-600', 'num' => 'text-emerald-700'],
        'blue'    => ['chip' => 'bg-blue-100 text-blue-600',       'num' => 'text-blue-700'],
        'amber'   => ['chip' => 'bg-amber-100 text-amber-600',     'num' => 'text-amber-700'],
        'rose'    => ['chip' => 'bg-rose-100 text-rose-600',       'num' => 'text-rose-700'],
        'orange'  => ['chip' => 'bg-orange-100 text-orange-600',   'num' => 'text-orange-700'],
        'indigo'  => ['chip' => 'bg-indigo-100 text-indigo-600',   'num' => 'text-indigo-700'],
        'slate'   => ['chip' => 'bg-slate-100 text-slate-600',     'num' => 'text-slate-700'],
    ];

    $iconPaths = [
        'cash'     => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'check'    => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'play'     => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'clock'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'x'        => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'userx'    => 'M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a7 7 0 00-7 7h7m9-9l-3 3m0 0l-3-3m3 3V8',
        'hours'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    ];

    // The headline KPI cards in display order.
    $kpis = [
        ['label' => __('dashboard.stats.paid_revenue'),    'value' => $fmtMoney($stats['paid_revenue']), 'accent' => 'emerald', 'icon' => 'cash',     'hero' => true],
        ['label' => __('dashboard.stats.completed'),       'value' => $stats['completed'],               'accent' => 'blue',    'icon' => 'check'],
        ['label' => __('dashboard.stats.in_progress_now'), 'value' => $stats['in_progress_now'],         'accent' => 'indigo',  'icon' => 'play',  'live' => true],
        ['label' => __('dashboard.stats.upcoming'),        'value' => $stats['upcoming'],                'accent' => 'amber',   'icon' => 'clock', 'live' => true],
        ['label' => __('dashboard.stats.booked_hours'),    'value' => $fmtHours($stats['booked_minutes']), 'accent' => 'slate', 'icon' => 'hours'],
        ['label' => __('dashboard.stats.total_bookings'),  'value' => $stats['total_bookings'],          'accent' => 'slate',   'icon' => 'calendar'],
        ['label' => __('dashboard.stats.cancelled'),       'value' => $stats['cancelled'],               'accent' => 'rose',    'icon' => 'x'],
        ['label' => __('dashboard.stats.no_show'),         'value' => $stats['no_show'],                 'accent' => 'orange',  'icon' => 'userx'],
    ];

    $sourceTotal = max(1, $stats['source_online'] + $stats['source_in_person']);
    $appPct = round($stats['source_online'] / $sourceTotal * 100);
    $maxServiceCount = collect($stats['services'])->max('count') ?: 1;
@endphp

<div class="h-screen flex flex-col bg-gray-50" @if ($isToday) wire:poll.30s @endif>

    {{-- ══════════════════════════════════ HEADER ══════════════════════════════════ --}}
    @include('partials.staff-nav', ['active' => 'stats'])

    {{-- ══════════════════════════════════ DATE / SCOPE BAR ════════════════════════ --}}
    <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex-shrink-0 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="previousDay"
                class="p-2 text-gray-400 hover:text-amber-600 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="{{ $rtl ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7' }}" />
                </svg>
            </button>

            <div class="text-center min-w-[12rem]">
                <h2 class="text-sm font-semibold text-gray-800">
                    {{ \Illuminate\Support\Carbon::parse($selectedDate)->locale(app()->getLocale())->isoFormat('dddd, D MMM YYYY') }}
                </h2>
                @if ($isToday)
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        {{ __('dashboard.stats.live') }}
                    </span>
                @endif
            </div>

            <button type="button" wire:click="nextDay"
                class="p-2 text-gray-400 hover:text-amber-600 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="{{ $rtl ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' }}" />
                </svg>
            </button>

            <input type="date" wire:model.live="selectedDate"
                class="ms-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:ring-amber-500 focus:border-amber-500 outline-none" />

            @if (! $isToday)
                <button type="button" wire:click="goToToday"
                    class="px-3 py-1.5 text-xs font-medium text-amber-600 hover:text-amber-700 rounded-lg hover:bg-amber-50 transition">
                    {{ __('dashboard.stats.today') }}
                </button>
            @endif
        </div>

        <div class="flex items-center gap-2">
            {{-- Scope badge --}}
            @if ($isSalonScope)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold border border-amber-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    {{ __('dashboard.stats.salon_wide') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold border border-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ __('dashboard.stats.my_stats') }}
                </span>
            @endif

            <button type="button" wire:click="$refresh" wire:loading.attr="disabled"
                class="p-2 text-gray-400 hover:text-amber-600 hover:bg-gray-100 rounded-lg transition"
                title="{{ __('dashboard.stats.refresh') }}">
                <svg class="w-5 h-5" wire:loading.class="animate-spin" wire:target="$refresh" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════ CONTENT ═════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">

        {{-- ── KPI GRID ───────────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                @php $a = $accents[$kpi['accent']]; @endphp
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-start gap-3 {{ ($kpi['hero'] ?? false) ? 'ring-1 ring-emerald-200' : '' }}">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 {{ $a['chip'] }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths[$kpi['icon']] }}" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide flex items-center gap-1">
                            {{ $kpi['label'] }}
                            @if (($kpi['live'] ?? false) && $isToday)
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            @endif
                        </p>
                        <p class="mt-1 text-xl sm:text-2xl font-bold {{ $a['num'] }} truncate">{{ $kpi['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── SECONDARY ROW: source split + money detail + services ──────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Booking source: app vs reception --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('dashboard.stats.source_title') }}</h3>

                <div class="flex items-end justify-between mb-2">
                    <div>
                        <p class="text-2xl font-bold text-violet-600">{{ $stats['source_online'] }}</p>
                        <p class="text-xs text-gray-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            {{ __('dashboard.stats.source_app') }}
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="text-2xl font-bold text-teal-600">{{ $stats['source_in_person'] }}</p>
                        <p class="text-xs text-gray-500 flex items-center gap-1 justify-end">
                            {{ __('dashboard.stats.source_reception') }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M3 7v1a3 3 0 006 0V7m0 1a3 3 0 006 0V7m0 1a3 3 0 006 0V7H3l2-4h14l2 4M5 21V10.85M19 21V10.85" />
                            </svg>
                        </p>
                    </div>
                </div>

                <div class="w-full h-2.5 rounded-full overflow-hidden bg-teal-100 flex">
                    <div class="h-full bg-violet-500" style="width: {{ $appPct }}%"></div>
                </div>
                <div class="flex justify-between mt-1.5 text-[11px] text-gray-400">
                    <span>{{ __('dashboard.stats.source_app') }} {{ $appPct }}%</span>
                    <span>{{ __('dashboard.stats.source_reception') }} {{ 100 - $appPct }}%</span>
                </div>
            </div>

            {{-- Money detail --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('dashboard.stats.money_title') }}</h3>
                <dl class="space-y-3">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">{{ __('dashboard.stats.collected') }}</dt>
                        <dd class="text-sm font-semibold text-emerald-600">{{ $fmtMoney($stats['paid_revenue']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">{{ __('dashboard.stats.outstanding') }}</dt>
                        <dd class="text-sm font-semibold text-amber-600">{{ $fmtMoney($stats['outstanding']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                        <dt class="text-sm text-gray-500">{{ __('dashboard.stats.paid_count') }}</dt>
                        <dd class="text-sm font-semibold text-gray-700">{{ $stats['paid_count'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">{{ __('dashboard.stats.avg_ticket') }}</dt>
                        <dd class="text-sm font-semibold text-gray-700">{{ $fmtMoney($stats['avg_ticket']) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Services delivered today --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('dashboard.stats.services_title') }}</h3>
                @if (empty($stats['services']))
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('dashboard.stats.services_empty') }}</p>
                @else
                    <ul class="space-y-3 max-h-64 overflow-y-auto pe-1">
                        @foreach ($stats['services'] as $service)
                            <li>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-700 truncate me-2">{{ $service['name'] }}</span>
                                    <span class="text-xs font-medium text-gray-500 whitespace-nowrap">
                                        {{ $service['count'] }}× · {{ $fmtMoney($service['revenue']) }}
                                    </span>
                                </div>
                                <div class="w-full h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-amber-400 rounded-full" style="width: {{ round($service['count'] / $maxServiceCount * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- ── PER-PROVIDER BREAKDOWN (salon scope only) ──────────────────────────── --}}
        @if ($isSalonScope)
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('dashboard.stats.breakdown_title') }}</h3>
                </div>

                @if (empty($breakdown))
                    <p class="text-sm text-gray-400 py-10 text-center">{{ __('dashboard.stats.no_providers') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50">
                                    <th class="px-5 py-2.5 text-start font-medium">{{ __('dashboard.stats.col_provider') }}</th>
                                    <th class="px-3 py-2.5 text-center font-medium">{{ __('dashboard.stats.col_bookings') }}</th>
                                    <th class="px-3 py-2.5 text-center font-medium">{{ __('dashboard.stats.col_completed') }}</th>
                                    <th class="px-3 py-2.5 text-center font-medium">{{ __('dashboard.stats.col_cancelled') }}</th>
                                    <th class="px-3 py-2.5 text-center font-medium">{{ __('dashboard.stats.col_hours') }}</th>
                                    <th class="px-3 py-2.5 text-center font-medium">{{ __('dashboard.stats.col_source') }}</th>
                                    <th class="px-5 py-2.5 text-end font-medium">{{ __('dashboard.stats.col_revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($breakdown as $row)
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center text-xs font-semibold overflow-hidden flex-shrink-0">
                                                    @if ($row['provider_avatar'])
                                                        <img src="{{ $row['provider_avatar'] }}" alt="" class="w-full h-full object-cover">
                                                    @else
                                                        {{ mb_substr($row['provider_name'], 0, 1) }}
                                                    @endif
                                                </div>
                                                <span class="font-medium text-gray-700 truncate">{{ $row['provider_name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-center text-gray-600">{{ $row['total_bookings'] }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="text-emerald-600 font-medium">{{ $row['completed'] }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            @if ($row['cancelled'] > 0)
                                                <span class="text-rose-600 font-medium">{{ $row['cancelled'] }}</span>
                                            @else
                                                <span class="text-gray-300">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-center text-gray-600">{{ $fmtHours($row['booked_minutes']) }}</td>
                                        <td class="px-3 py-3 text-center text-gray-500 text-xs whitespace-nowrap">
                                            <span class="text-violet-600 font-medium">{{ $row['source_online'] }}</span>
                                            <span class="text-gray-300">/</span>
                                            <span class="text-teal-600 font-medium">{{ $row['source_in_person'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-end font-semibold text-emerald-700 whitespace-nowrap">{{ $fmtMoney($row['paid_revenue']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- ── EMPTY DAY HINT (personal scope only; the salon table already shows
               idle providers, so the hint would be redundant there) ────────────── --}}
        @if (! $isSalonScope && $stats['total_bookings'] === 0 && $stats['cancelled'] === 0)
            <div class="text-center py-8">
                <p class="text-sm text-gray-400">{{ __('dashboard.stats.empty_day') }}</p>
            </div>
        @endif
    </div>
</div>
