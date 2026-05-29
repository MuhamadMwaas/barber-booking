<div class="h-screen flex flex-col bg-gray-50">

    {{-- ══════════════════════════════════ HEADER ══════════════════════════════════ --}}
    @include('partials.staff-nav', ['active' => 'customers'])

    {{-- ══════════════════════════════════ SEARCH BAR ═════════════════════════════ --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex-shrink-0 shadow-sm">
        <form wire:submit="performSearch" class="flex items-center gap-3 max-w-2xl">
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 {{ app()->getLocale() === 'ar' ? 'right-0 pr-3' : 'left-0 pl-3' }} flex items-center">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model="search"
                    placeholder="{{ __('dashboard.customer_lookup.search_placeholder') }}"
                    autocomplete="off"
                    class="{{ app()->getLocale() === 'ar' ? 'pr-9 pl-4' : 'pl-9 pr-4' }} w-full border border-gray-300 rounded-lg py-2 text-sm text-gray-700 placeholder-gray-400 focus:ring-amber-500 focus:border-amber-500 outline-none"
                />
            </div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="performSearch"
                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                <span wire:loading.remove wire:target="performSearch">{{ __('dashboard.customer_lookup.search_button') }}</span>
                <span wire:loading wire:target="performSearch" class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    {{ __('dashboard.customer_lookup.searching') }}
                </span>
            </button>
            @if ($searched)
                <button
                    type="button"
                    wire:click="clearSearch"
                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    {{ __('dashboard.customer_lookup.clear') }}
                </button>
            @endif
        </form>
    </div>

    {{-- ══════════════════════════════════ MAIN CONTENT ════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto">
        <div wire:loading.flex wire:target="performSearch" class="h-full flex-col items-center justify-center text-center px-4 py-16">
            <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-amber-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-700">{{ __('dashboard.customer_lookup.searching') }}</h2>
            <p class="text-gray-400 text-sm mt-1">{{ __('dashboard.customer_lookup.searching_hint') }}</p>
        </div>

        <div wire:loading.remove wire:target="performSearch" class="h-full">

        {{-- ─────────────── STATE 1: Not searched yet ─────────────── --}}
        @if (! $searched)
            <div class="flex flex-col items-center justify-center h-full text-center px-4 py-16">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-700 mb-2">{{ __('dashboard.customer_lookup.start_search') }}</h2>
                <p class="text-gray-400 text-sm max-w-sm">{{ __('dashboard.customer_lookup.start_search_hint') }}</p>
            </div>

        {{-- ─────────────── STATE 2: Customer selected → show appointments ─────────────── --}}
        @elseif ($selectedCustomerId)
            <div class="max-w-4xl mx-auto px-4 py-6">
                {{-- Customer header + back button --}}
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <button
                            wire:click="backToResults"
                            class="text-sm text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1 mb-2">
                            <svg class="w-4 h-4 {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            {{ __('dashboard.customer_lookup.back_to_results') }}
                        </button>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center text-amber-700 font-semibold text-sm flex-shrink-0">
                                {{ mb_substr($selectedCustomerInfo['name'] ?? 'C', 0, 1) }}
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                    {{ $selectedCustomerInfo['name'] }}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                        {{ __('dashboard.customer_lookup.registered_badge') }}
                                    </span>
                                </h2>
                                <div class="flex items-center gap-3 mt-0.5 text-sm text-gray-500 flex-wrap">
                                    @if ($selectedCustomerInfo['phone'])
                                        <span>{{ $selectedCustomerInfo['phone'] }}</span>
                                    @endif
                                    @if ($selectedCustomerInfo['email'])
                                        <span>{{ $selectedCustomerInfo['email'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="text-sm text-gray-400 mt-2">
                        {{ __('dashboard.customer_lookup.bookings_count', ['count' => count($customerAppointments)]) }}
                    </span>
                </div>

                {{-- Appointments list --}}
                @if (empty($customerAppointments))
                    <div class="text-center py-12 text-gray-400 text-sm">
                        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        {{ __('dashboard.customer_lookup.no_bookings') }}
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($customerAppointments as $apt)
                            @php
                                $statusColor = match($apt['status']) {
                                    0  => ['bg' => 'bg-amber-50 border-l-amber-400',  'badge' => 'bg-amber-100 text-amber-700'],
                                    1  => ['bg' => 'bg-green-50 border-l-green-400',  'badge' => 'bg-green-100 text-green-700'],
                                    -1, -2 => ['bg' => 'bg-red-50 border-l-red-400', 'badge' => 'bg-red-100 text-red-700'],
                                    -3 => ['bg' => 'bg-slate-50 border-l-slate-400',  'badge' => 'bg-slate-200 text-slate-600'],
                                    default => ['bg' => 'bg-gray-50 border-l-gray-300', 'badge' => 'bg-gray-100 text-gray-600'],
                                };
                                $payColor = match($apt['payment_status']) {
                                    1, 2, 3 => 'bg-green-100 text-green-700',
                                    default => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <button
                                wire:click="viewAppointment({{ $apt['id'] }})"
                                class="w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} bg-white border border-gray-100 border-{{ app()->getLocale() === 'ar' ? 'r' : 'l' }}-4 {{ $statusColor['bg'] }} rounded-lg px-4 py-3 hover:shadow-sm transition-all group">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="text-sm font-semibold text-gray-800">
                                            {{ $apt['appointment_date_fmt'] }}
                                            <span class="font-normal text-gray-500 text-xs ms-1">{{ $apt['start_time'] }}{{ $apt['end_time'] ? ' – ' . $apt['end_time'] : '' }}</span>
                                        </div>
                                        <div class="hidden sm:block text-sm text-gray-600 truncate max-w-xs">
                                            {{ $apt['services_summary'] ?: '—' }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @if ($apt['has_notes'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-600">
                                                <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5"/></svg>
                                                {{ __('dashboard.customer_lookup.has_notes_badge') }}
                                            </span>
                                        @endif
                                        @if ($apt['colors_count'] > 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-600">
                                                <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4z"/></svg>
                                                {{ __('dashboard.customer_lookup.colors_badge', ['count' => $apt['colors_count']]) }}
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor['badge'] }}">
                                            {{ $apt['status_label'] }}
                                        </span>
                                        <span class="text-sm font-semibold text-gray-700">
                                            {{ number_format($apt['total_amount'], 2) }} €
                                        </span>
                                        <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-400 transition-colors {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="sm:hidden mt-1 text-xs text-gray-500 truncate">
                                    {{ $apt['services_summary'] ?: '—' }}
                                </div>
                                @if ($apt['provider_name'])
                                    <div class="mt-1 text-xs text-gray-400">{{ $apt['provider_name'] }}</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

        {{-- ─────────────── STATE 3: Search results ─────────────── --}}
        @else
            <div class="max-w-4xl mx-auto px-4 py-6 space-y-8">

                {{-- No results at all --}}
                @if (empty($registeredResults) && empty($guestResults))
                    <div class="text-center py-12">
                        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <p class="text-gray-500 font-medium text-sm">{{ __('dashboard.customer_lookup.no_results') }}</p>
                        <p class="text-gray-400 text-xs mt-1">{{ __('dashboard.customer_lookup.no_results_hint') }}</p>
                    </div>
                @endif

                {{-- ── Registered Customers ── --}}
                @if (! empty($registeredResults))
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>
                                {{ __('dashboard.customer_lookup.registered_customers') }}
                                <span class="text-gray-400 font-normal normal-case tracking-normal text-xs">({{ count($registeredResults) }})</span>
                            </h3>
                        </div>
                        @if ($registeredCapped)
                            <p class="text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded mb-3">
                                {{ __('dashboard.customer_lookup.results_capped', ['count' => 25]) }}
                            </p>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($registeredResults as $customer)
                                <button
                                    wire:click="selectCustomer({{ $customer['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="selectCustomer({{ $customer['id'] }})"
                                    class="text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} bg-white border border-gray-200 rounded-xl px-4 py-3 hover:border-amber-300 hover:shadow-sm transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 bg-amber-100 rounded-full flex items-center justify-center text-amber-700 font-semibold text-sm flex-shrink-0">
                                            {{ mb_substr($customer['name'], 0, 1) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold text-gray-800 text-sm group-hover:text-amber-700 transition-colors truncate">
                                                {{ $customer['name'] }}
                                            </div>
                                            <div class="text-xs text-gray-400 truncate mt-0.5">
                                                {{ $customer['phone'] }}
                                                @if ($customer['email'])
                                                    <span class="mx-1">·</span>{{ $customer['email'] }}
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 text-right">
                                            <div class="text-xs font-medium text-gray-500">
                                                {{ __('dashboard.customer_lookup.bookings_count', ['count' => $customer['appointments_count']]) }}
                                            </div>
                                            <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-400 transition-colors mt-1 ms-auto {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ── Guest Bookings ── --}}
                @if (! empty($guestResults))
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
                                {{ __('dashboard.customer_lookup.guest_bookings') }}
                                <span class="text-gray-400 font-normal normal-case tracking-normal text-xs">({{ count($guestResults) }})</span>
                            </h3>
                        </div>
                        @if ($guestCapped)
                            <p class="text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded mb-3">
                                {{ __('dashboard.customer_lookup.results_capped', ['count' => 50]) }}
                            </p>
                        @endif
                        <div class="space-y-2">
                            @foreach ($guestResults as $apt)
                                @php
                                    $statusColor = match($apt['status']) {
                                        0  => ['border' => 'border-l-amber-400',  'badge' => 'bg-amber-100 text-amber-700'],
                                        1  => ['border' => 'border-l-green-400',  'badge' => 'bg-green-100 text-green-700'],
                                        -1, -2 => ['border' => 'border-l-red-400','badge' => 'bg-red-100 text-red-700'],
                                        -3 => ['border' => 'border-l-slate-400',  'badge' => 'bg-slate-200 text-slate-600'],
                                        default => ['border' => 'border-l-gray-300','badge' => 'bg-gray-100 text-gray-600'],
                                    };
                                @endphp
                                <button
                                    wire:click="viewAppointment({{ $apt['id'] }})"
                                    class="w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} bg-white border border-gray-100 border-{{ app()->getLocale() === 'ar' ? 'r' : 'l' }}-4 {{ $statusColor['border'] }} rounded-lg px-4 py-3 hover:shadow-sm transition-all group">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 font-semibold text-xs flex-shrink-0">
                                                {{ mb_substr($apt['customer_name'], 0, 1) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-800 flex items-center gap-2 flex-wrap">
                                                    {{ $apt['customer_name'] }}
                                                    <span class="text-xs font-normal px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded">{{ __('dashboard.customer_lookup.guest_badge') }}</span>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-0.5">
                                                    @if ($apt['customer_phone']) {{ $apt['customer_phone'] }} @endif
                                                    @if ($apt['customer_phone'] && $apt['customer_email']) · @endif
                                                    @if ($apt['customer_email']) {{ $apt['customer_email'] }} @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            @if ($apt['has_notes'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-600">
                                                    {{ __('dashboard.customer_lookup.has_notes_badge') }}
                                                </span>
                                            @endif
                                            @if ($apt['colors_count'] > 0)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-600">
                                                    {{ __('dashboard.customer_lookup.colors_badge', ['count' => $apt['colors_count']]) }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor['badge'] }}">
                                                {{ $apt['status_label'] }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-400 transition-colors {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2 text-xs text-gray-500">
                                        <span class="font-medium">{{ $apt['appointment_date_fmt'] }}</span>
                                        @if ($apt['start_time']) <span>{{ $apt['start_time'] }}</span> @endif
                                        @if ($apt['provider_name']) <span class="text-gray-400">· {{ $apt['provider_name'] }}</span> @endif
                                        @if ($apt['services_summary']) <span class="text-gray-400 truncate">· {{ $apt['services_summary'] }}</span> @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        @endif

        </div>
    </div>{{-- end main content --}}

    {{-- ══════════════════════ APPOINTMENT DETAIL MODAL (Read-Only) ════════════════ --}}
    @if ($selectedAppointmentId && $selectedAppointment)
        @php
            $apt = $selectedAppointment;

            $statusBadge = match ($apt->status->value) {
                0       => ['wrapper' => 'bg-amber-100 text-amber-800',  'dot' => 'bg-amber-500'],
                1       => ['wrapper' => 'bg-green-100 text-green-800',  'dot' => 'bg-green-500'],
                -1, -2  => ['wrapper' => 'bg-red-100 text-red-800',      'dot' => 'bg-red-500'],
                -3      => ['wrapper' => 'bg-slate-200 text-slate-700',  'dot' => 'bg-slate-500'],
                default => ['wrapper' => 'bg-gray-100 text-gray-700',   'dot' => 'bg-gray-400'],
            };

            $paymentBadge = match ($apt->payment_status->value) {
                1, 2, 3 => ['wrapper' => 'bg-green-100 text-green-800', 'dot' => 'bg-green-500'],
                4       => ['wrapper' => 'bg-red-100 text-red-800',     'dot' => 'bg-red-500'],
                5, 6    => ['wrapper' => 'bg-sky-100 text-sky-800',     'dot' => 'bg-sky-500'],
                default => ['wrapper' => 'bg-amber-100 text-amber-800', 'dot' => 'bg-amber-500'],
            };
        @endphp

        <div
            class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
            wire:click.self="closeAppointment">
            <div
                class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col"
                style="max-height: 88vh;"
                @click.stop>

                {{-- Modal Header --}}
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">{{ __('dashboard.customer_lookup.detail_title') }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">#{{ $apt->number }}</p>
                    </div>
                    <button wire:click="closeAppointment" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body (scrollable) --}}
                <div class="overflow-y-auto flex-1 p-5 space-y-4">

                    {{-- ── Core Info ── --}}
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        {{-- Date/Time --}}
                        <div class="col-span-2 flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.customer_lookup.date_time_label') }}</span>
                            <span class="font-medium text-gray-800">
                                {{ $apt->appointment_date?->format('d M Y') }}
                                <span class="text-gray-500 font-normal">
                                    · {{ $apt->start_time?->format('H:i') }}–{{ $apt->end_time?->format('H:i') }}
                                </span>
                            </span>
                        </div>
                        {{-- Customer --}}
                        <div class="col-span-2 flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.customer_lookup.customer_section') }}</span>
                            <span class="font-medium text-gray-800 flex items-center gap-1.5">
                                @if ($apt->customer_id)
                                    <span class="text-amber-500 text-xs font-bold">@</span>
                                @endif
                                {{ $apt->customer_name }}
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $apt->customer_id ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $apt->customer_id ? __('dashboard.customer_lookup.registered_badge') : __('dashboard.customer_lookup.guest_badge') }}
                                </span>
                            </span>
                        </div>
                        {{-- Provider --}}
                        <div class="col-span-2 flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.customer_lookup.provider_label') }}</span>
                            <span class="font-medium text-gray-800">{{ $apt->provider?->full_name ?? '—' }}</span>
                        </div>
                        {{-- Status + Payment --}}
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-500 text-xs">{{ __('dashboard.appointment_modal.status') }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge['wrapper'] }} w-fit">
                                <span class="h-2 w-2 rounded-full {{ $statusBadge['dot'] }}"></span>
                                {{ $apt->status->getLabel() }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-500 text-xs">{{ __('dashboard.customer_lookup.payment_label') }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentBadge['wrapper'] }} w-fit">
                                <span class="h-2 w-2 rounded-full {{ $paymentBadge['dot'] }}"></span>
                                {{ $apt->payment_status->label() }}
                            </span>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- ── Services ── --}}
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('dashboard.customer_lookup.services_section') }}</p>
                        <div class="space-y-1.5">
                            @forelse ($apt->services_record->sortBy('sequence_order') as $sr)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                    <span class="text-sm font-medium text-gray-700 truncate">{{ $sr->service_name }}</span>
                                    <span class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                                        <span>{{ $sr->duration_minutes }} {{ __('dashboard.appointment_modal.minutes_short') }}</span>
                                        <span class="text-gray-300">|</span>
                                        <span>{{ number_format((float) $sr->price, 2) }} €</span>
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic">—</p>
                            @endforelse
                            {{-- Total --}}
                            <div class="flex justify-between items-center pt-1 px-1">
                                <span class="text-sm font-semibold text-gray-700">{{ __('dashboard.customer_lookup.total_label') }}</span>
                                <span class="text-sm font-bold text-gray-800">{{ number_format((float) $apt->total_amount, 2) }} €</span>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- ── Customer Notes (read-only) ── --}}
                    <div x-data="{ open: {{ $apt->notes ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-sm font-medium text-gray-700 transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                {{ __('dashboard.customer_lookup.notes_section') }}
                                @if ($apt->notes)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">{{ __('dashboard.appointment_modal.has_notes') }}</span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 px-3 py-2 bg-gray-50 rounded-lg text-sm text-gray-700 min-h-[40px]">
                            @if ($apt->notes)
                                <p class="whitespace-pre-wrap">{{ $apt->notes }}</p>
                            @else
                                <p class="text-gray-400 italic text-xs">{{ __('dashboard.customer_lookup.no_notes') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- ── Provider Notes (read-only, blue) ── --}}
                    <div x-data="{ open: {{ $apt->provider_notes ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-sm font-medium text-blue-700 transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                {{ __('dashboard.customer_lookup.provider_notes_section') }}
                                @if ($apt->provider_notes)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700">{{ __('dashboard.appointment_modal.has_notes') }}</span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-blue-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 px-3 py-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-gray-700 min-h-[40px]">
                            @if ($apt->provider_notes)
                                <p class="whitespace-pre-wrap">{{ $apt->provider_notes }}</p>
                            @else
                                <p class="text-gray-400 italic text-xs">{{ __('dashboard.customer_lookup.no_provider_notes') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- ── Colors Used (read-only, purple) ── --}}
                    <div x-data="{ open: {{ $apt->colorRecords->isNotEmpty() ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-purple-50 hover:bg-purple-100 text-sm font-medium text-purple-700 transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                </svg>
                                {{ __('dashboard.customer_lookup.colors_section') }}
                                @if ($apt->colorRecords->isNotEmpty())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700">
                                        {{ $apt->colorRecords->count() }}
                                    </span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-purple-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 space-y-1.5">
                            @forelse ($apt->colorRecords as $colorRecord)
                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-100 rounded-lg">
                                    <span class="w-5 h-5 rounded flex-shrink-0 border border-gray-200"
                                          style="background-color: {{ $colorRecord->color->hex_code ?? '#ccc' }}"></span>
                                    <span class="flex-1 text-sm font-medium text-gray-700">
                                        {{ $colorRecord->color->name ?? '—' }}
                                        @if ($colorRecord->color?->brand)
                                            <span class="text-xs text-gray-400">({{ $colorRecord->color->brand }})</span>
                                        @endif
                                    </span>
                                    <span class="text-sm text-gray-500 flex-shrink-0">
                                        {{ number_format($colorRecord->quantity, 2) }} {{ $colorRecord->color->unit ?? '' }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic px-3 py-2">{{ __('dashboard.customer_lookup.no_colors') }}</p>
                            @endforelse
                        </div>
                    </div>

                </div>{{-- end modal body --}}

                {{-- Modal Footer --}}
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end flex-shrink-0">
                    <button
                        wire:click="closeAppointment"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        {{ __('dashboard.customer_lookup.close') }}
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
