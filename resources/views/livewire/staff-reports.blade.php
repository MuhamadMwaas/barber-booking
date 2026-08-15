@php
    // Presentation-only helpers — every number arrives pre-computed from
    // DailyReportService via the component.
    $rtl = app()->getLocale() === 'ar';
    $currency = $preview['meta']['currency'] ?? '€';
    $money = fn ($v) => $currency . ' ' . number_format((float) $v, 2);

    $presets = ['today', 'yesterday', 'this_week', 'this_month', 'last_month'];
@endphp

<div class="h-screen flex flex-col bg-gray-50">

    {{-- ═══════════════════════════ HEADER ═══════════════════════════ --}}
    @include('partials.staff-nav', ['active' => 'reports'])

    <div class="flex-1 overflow-y-auto p-4 sm:p-6">
        <div class="max-w-5xl mx-auto space-y-5">

            {{-- ── Title ────────────────────────────────────────────── --}}
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ __('z_report.tab_title') }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('z_report.tab_subtitle') }}</p>
            </div>

            {{-- ── Filters ──────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-5">

                {{-- Period mode --}}
                <div>
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2">
                        {{ __('z_report.mode') }}
                    </p>
                    <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                        @foreach (['day', 'range'] as $option)
                            <button type="button" wire:click="$set('mode', '{{ $option }}')"
                                class="px-4 py-2 text-sm font-medium transition
                                    {{ $mode === $option
                                        ? 'bg-amber-500 text-white'
                                        : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                                {{ __('z_report.mode_' . $option) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Dates --}}
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">
                            {{ $mode === 'day' ? __('z_report.date') : __('z_report.from') }}
                        </label>
                        <input type="date" wire:model.live="fromDate"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-amber-500 focus:border-amber-500 outline-none" />
                    </div>

                    @if ($mode === 'range')
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">
                                {{ __('z_report.to') }}
                            </label>
                            <input type="date" wire:model.live="toDate"
                                class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-amber-500 focus:border-amber-500 outline-none" />
                        </div>
                    @endif

                    <div class="flex-1 min-w-[16rem]">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">
                            {{ __('z_report.quick_ranges') }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($presets as $preset)
                                <button type="button" wire:click="applyPreset('{{ $preset }}')"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-amber-100 hover:text-amber-700 transition">
                                    {{ __('z_report.' . $preset) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Employees --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                            {{ __('z_report.employees') }}
                            <span class="ms-1 normal-case text-gray-400 font-normal">
                                @if (empty($selectedProviderIds))
                                    — {{ __('z_report.all_employees') }}
                                @else
                                    — {{ __('z_report.employees_selected', ['count' => count($selectedProviderIds)]) }}
                                @endif
                            </span>
                        </p>
                        <div class="flex gap-2">
                            <button type="button" wire:click="selectAllProviders"
                                class="text-xs font-medium text-amber-600 hover:text-amber-700">
                                {{ __('z_report.select_all') }}
                            </button>
                            <span class="text-gray-200">|</span>
                            <button type="button" wire:click="clearProviders"
                                class="text-xs font-medium text-gray-400 hover:text-gray-600">
                                {{ __('z_report.clear') }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($providers as $provider)
                            @php $checked = in_array($provider['id'], $selectedProviderIds); @endphp
                            <label
                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm cursor-pointer transition
                                    {{ $checked
                                        ? 'bg-amber-50 border-amber-300 text-amber-800'
                                        : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                                <input type="checkbox" wire:model.live="selectedProviderIds"
                                    value="{{ $provider['id'] }}"
                                    class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                                {{ $provider['name'] }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Generate --}}
                <div class="flex flex-wrap items-center gap-3 pt-1 border-t border-gray-100">
                    @if ($rangeError)
                        <p class="text-sm font-medium text-rose-600 pt-4">{{ $rangeError }}</p>
                    @else
                        <a href="{{ $reportUrl }}" target="_blank" rel="noopener"
                            class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500 text-white text-sm font-semibold rounded-lg hover:bg-amber-600 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            {{ __('z_report.generate') }}
                        </a>
                        <span class="mt-4 text-xs text-gray-400">{{ __('z_report.generate_hint') }}</span>
                    @endif
                </div>
            </div>

            {{-- ── Preview ──────────────────────────────────────────── --}}
            @if ($preview)
                @php
                    $sales = $preview['sales'];
                    $totals = $preview['totals'];
                    $rows = $preview['employees']['rows'];
                    $empTotals = $preview['employees']['totals'];
                @endphp

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-700">{{ __('z_report.preview_title') }}</h2>
                            <p class="text-xs text-gray-400">{{ __('z_report.preview_hint') }}</p>
                        </div>
                        <div wire:loading.flex wire:target="fromDate,toDate,mode,selectedProviderIds,applyPreset"
                            class="items-center gap-1.5 text-xs text-amber-600">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                    </div>

                    {{-- Headline numbers --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 rtl:divide-x-reverse">
                        <div class="p-4">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">{{ __('z_report.total_sales') }}</p>
                            <p class="mt-1 text-xl font-bold text-emerald-600">{{ $money($sales['total_amount']) }}</p>
                        </div>
                        <div class="p-4">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">{{ __('z_report.cash') }}</p>
                            <p class="mt-1 text-xl font-bold text-gray-700">{{ $money($sales['buckets']['cash']['amount']) }}</p>
                        </div>
                        <div class="p-4">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">{{ __('z_report.card') }}</p>
                            <p class="mt-1 text-xl font-bold text-gray-700">{{ $money($sales['buckets']['card']['amount']) }}</p>
                        </div>
                        <div class="p-4">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">{{ __('z_report.total_transactions') }}</p>
                            <p class="mt-1 text-xl font-bold text-gray-700">{{ $totals['transactions'] }}</p>
                        </div>
                    </div>

                    {{-- Employee summary --}}
                    @if (empty($rows))
                        <p class="text-sm text-gray-400 py-10 text-center border-t border-gray-100">
                            {{ __('z_report.employees_empty') }}
                        </p>
                    @else
                        <div class="overflow-x-auto border-t border-gray-100">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50">
                                        <th class="px-5 py-2.5 text-start font-medium">{{ __('z_report.employee') }}</th>
                                        <th class="px-3 py-2.5 text-center font-medium">{{ __('z_report.appointments') }}</th>
                                        <th class="px-3 py-2.5 text-center font-medium">{{ __('z_report.services') }}</th>
                                        <th class="px-3 py-2.5 text-center font-medium">{{ __('z_report.cash_sales') }}</th>
                                        <th class="px-3 py-2.5 text-center font-medium">{{ __('z_report.card_sales') }}</th>
                                        <th class="px-5 py-2.5 text-end font-medium">{{ __('z_report.total_revenue') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($rows as $row)
                                        <tr class="hover:bg-gray-50/70 transition-colors">
                                            <td class="px-5 py-2.5">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-full bg-amber-500 text-white flex items-center justify-center text-[11px] font-semibold overflow-hidden flex-shrink-0">
                                                        @if ($row['avatar'])
                                                            <img src="{{ $row['avatar'] }}" alt="" class="w-full h-full object-cover">
                                                        @else
                                                            {{ mb_substr($row['provider_name'], 0, 1) }}
                                                        @endif
                                                    </div>
                                                    <span class="font-medium text-gray-700 truncate">{{ $row['provider_name'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2.5 text-center text-gray-600">{{ $row['appointments'] }}</td>
                                            <td class="px-3 py-2.5 text-center text-gray-600">{{ $row['services'] }}</td>
                                            <td class="px-3 py-2.5 text-center text-gray-600">{{ $money($row['cash']) }}</td>
                                            <td class="px-3 py-2.5 text-center text-gray-600">{{ $money($row['card']) }}</td>
                                            <td class="px-5 py-2.5 text-end font-semibold text-emerald-700 whitespace-nowrap">
                                                {{ $money($row['total']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50 font-semibold text-gray-700 border-t-2 border-gray-200">
                                        <td class="px-5 py-2.5">{{ __('z_report.total_row') }}</td>
                                        <td class="px-3 py-2.5 text-center">{{ $empTotals['appointments'] }}</td>
                                        <td class="px-3 py-2.5 text-center">{{ $empTotals['services'] }}</td>
                                        <td class="px-3 py-2.5 text-center">{{ $money($empTotals['cash']) }}</td>
                                        <td class="px-3 py-2.5 text-center">{{ $money($empTotals['card']) }}</td>
                                        <td class="px-5 py-2.5 text-end text-emerald-700 whitespace-nowrap">{{ $money($empTotals['total']) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
