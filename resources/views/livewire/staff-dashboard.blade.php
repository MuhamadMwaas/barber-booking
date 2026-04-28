<div class="h-screen flex flex-col" x-data="dashboardApp()"
    @if (!$showAppointmentModal && !$showPaymentModal && !$showTimeOffModal) x-effect="if (!showBookingModal) { clearInterval(_pollTimer); _pollTimer = setInterval(() => $wire.$refresh(), 3000); } else { clearInterval(_pollTimer); }" x-init="_pollTimer = setInterval(() => $wire.$refresh(), 3000)" @endif
    x-on:booking-saved.window="showBookingModal = false; bookingSaving = false"
    x-on:booking-error.window="bookingSaving = false">
    {{-- Top Navigation --}}
    <header class="bg-white border-b border-gray-200 flex items-center justify-between px-4 py-2 flex-shrink-0">
        <div class="flex items-center space-x-6">
            <h1 class="text-lg font-bold text-gray-800 tracking-tight">{{ config('app.name') }}</h1>
            <nav class="flex space-x-1">
                <a href="/dashboard"
                    class="px-4 py-2 text-sm font-medium text-amber-600 border-b-2 border-amber-500">{{ __('dashboard.calendar') }}</a>
                {{-- <a href="#" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">{{ __('dashboard.checkout') }}</a> --}}
                {{-- <a href="#" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">{{ __('dashboard.customers') }}</a> --}}
                <a href="/admin"
                    class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">{{ __('dashboard.admin') }}</a>
            </nav>
        </div>
        <div class="flex items-center space-x-4">
            <div class="relative" x-data="{ languageOpen: false }">
                <button @click="languageOpen = !languageOpen"
                    class="flex items-center space-x-2 rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.5 21m3.548-6.5A18.021 18.021 0 0017.5 21M12 11a9 9 0 100-18 9 9 0 000 18zm0 0c2.485 0 4.5 4.03 4.5 9s-2.015 9-4.5 9-4.5-4.03-4.5-9 2.015-9 4.5-9z">
                        </path>
                    </svg>
                    <span class="font-medium uppercase">{{ app()->getLocale() }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div x-show="languageOpen" x-cloak @click.outside="languageOpen = false" x-transition
                    class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-52 rounded-lg border bg-white py-1 shadow-xl z-50">
                    @foreach ($activeLanguages as $language)
                        <a href="{{ url('/dashboard/language/' . $language['code']) }}"
                            class="flex items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 {{ app()->getLocale() === $language['code'] ? 'font-semibold text-amber-600' : 'text-gray-700' }}">
                            <span>{{ $language['native_name'] ?: $language['name'] }}</span>
                            <span class="text-xs uppercase text-gray-400">{{ $language['code'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            {{-- Notifications --}}
            <div class="relative" x-data="{ notifOpen: false }">
                <button @click="notifOpen = !notifOpen"
                    class="relative p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                        </path>
                    </svg>
                </button>
                <div x-show="notifOpen" @click.outside="notifOpen = false" x-transition
                    class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-xl border z-50 p-4">
                    <h3 class="font-semibold text-sm text-gray-700 mb-2">{{ __('dashboard.notifications') }}</h3>
                    <p class="text-sm text-gray-400">{{ __('dashboard.no_notifications') }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <div
                    class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                    {{ substr(auth()->user()->first_name ?? 'S', 0, 1) }}
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        {{-- Sidebar --}}
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 overflow-y-auto">
            {{-- Calendar --}}
            <div class="p-3 border-b border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <button wire:click="previousMonth" class="p-1 hover:bg-gray-100 rounded text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-700">
                        {{ __('dashboard.months.' . $calendarMonth) }} {{ $calendarYear }}
                    </span>
                    <button wire:click="nextMonth" class="p-1 hover:bg-gray-100 rounded text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-7 gap-0 text-center mb-1">
                    @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day)
                        <div class="text-xs font-medium text-gray-400 py-1">{{ __('dashboard.days.' . $day) }}</div>
                    @endforeach
                </div>
                @php
                    $firstDay = \Carbon\Carbon::create($calendarYear, $calendarMonth, 1);
                    $daysInMonth = $firstDay->daysInMonth;
                    $startDay = $firstDay->dayOfWeekIso - 1;
                    $today = \Carbon\Carbon::today()->format('Y-m-d');
                @endphp
                <div class="grid grid-cols-7 gap-0 text-center">
                    @for ($i = 0; $i < $startDay; $i++)
                        <div class="py-1"></div>
                    @endfor
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $dateStr = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $d);
                            $isToday = $dateStr === $today;
                            $isSelected = $dateStr === $selectedDate;
                            $count = $calendarCounts[$dateStr] ?? 0;
                        @endphp
                        <button wire:click="selectDate('{{ $dateStr }}')"
                            class="calendar-day relative py-1 text-xs rounded-md {{ $isSelected ? 'selected' : '' }} {{ $isToday ? 'today' : '' }} hover:bg-gray-100">
                            <span
                                class="{{ $isToday && !$isSelected ? 'text-amber-600 font-bold' : '' }}">{{ $d }}</span>
                            @if ($count > 0)
                                <span
                                    class="block text-[9px] leading-none {{ $isSelected ? 'text-amber-800' : 'text-gray-400' }}">{{ $count }}</span>
                            @endif
                        </button>
                    @endfor
                </div>
                <button wire:click="goToToday"
                    class="w-full mt-2 text-xs text-amber-600 hover:text-amber-700 font-medium py-1 rounded hover:bg-amber-50">
                    {{ __('dashboard.today') }}
                </button>
            </div>

            {{-- Providers --}}
            <div class="p-3 border-b border-gray-100 flex-1 overflow-y-auto">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                    {{ __('dashboard.team') }}</h3>
                <div class="space-y-1">
                    @foreach ($allProviders as $provider)
                        <label
                            class="provider-check flex items-center space-x-2 px-2 py-1.5 rounded-md hover:bg-gray-50 cursor-pointer {{ in_array($provider['id'], $selectedProviderIds) ? '' : 'opacity-50' }}">
                            <input type="checkbox" wire:click="toggleProvider({{ $provider['id'] }})"
                                {{ in_array($provider['id'], $selectedProviderIds) ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                            <div class="flex-1 min-w-0">
                                <span class="text-sm text-gray-700 block truncate">{{ $provider['name'] }}</span>
                                @if ($provider['has_day_off'])
                                    <span
                                        class="text-[10px] text-red-500 font-medium">{{ __('dashboard.on_leave') }}</span>
                                @elseif(!$provider['is_work_day'])
                                    <span
                                        class="text-[10px] text-gray-400 font-medium">{{ __('dashboard.not_working') }}</span>
                                @else
                                    <span class="text-[10px] text-gray-400">{{ $provider['booking_count'] }}
                                        {{ __('dashboard.bookings') }}</span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="p-3 space-y-2 flex-shrink-0">
                <button @click="openBookingModalLocal()"
                    class="w-full py-2 px-3 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                        </path>
                    </svg>
                    <span>{{ __('dashboard.add_booking') }}</span>
                </button>
                <button wire:click="openTimeOffModal"
                    class="w-full py-2 px-3 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                    <span>{{ __('dashboard.add_time_off') }}</span>
                </button>
            </div>
        </aside>

        {{-- Main Timeline Area --}}
        <main class="flex-1 flex flex-col overflow-hidden bg-gray-50">
            {{-- Date Header --}}
            <div class="bg-white border-b px-4 py-2 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <h2 class="text-sm font-semibold text-gray-700">
                        {{ \Carbon\Carbon::parse($selectedDate)->format('l, d M Y') }}
                    </h2>
                    @if ($selectedDate !== $today)
                        <button wire:click="goToToday"
                            class="px-2 py-1 text-xs text-amber-600 hover:text-amber-700 font-medium rounded hover:bg-amber-50">
                            {{ __('dashboard.today') }}
                        </button>
                    @endif
                    @if ($selectedDate === $today)
                        <span
                            class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full font-medium">{{ __('dashboard.today') }}</span>
                    @endif
                </div>
                <div class="flex items-center space-x-2">
                    <div class="relative" x-data="{ scaleMenuOpen: false }">
                        <button @click="scaleMenuOpen = !scaleMenuOpen"
                            class="flex items-center space-x-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-800">
                            <span>{{ __('dashboard.timeline.scale') }}</span>
                            <span x-text="timelineScaleLabel()"></span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="scaleMenuOpen" x-cloak @click.outside="scaleMenuOpen = false" x-transition
                            class="absolute right-0 z-40 mt-2 w-44 rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
                            <template x-for="option in timelineScaleOptions" :key="option.value">
                                <button type="button" @click="setTimelineScale(option.value); scaleMenuOpen = false"
                                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-xs text-gray-600 hover:bg-amber-50 hover:text-amber-700"
                                    :class="timelineScale === option.value ? 'bg-amber-50 text-amber-700' : ''">
                                    <span x-text="option.label"></span>
                                    <span x-show="timelineScale === option.value"
                                        class="text-amber-600">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400" wire:loading>
                        <svg class="animate-spin h-4 w-4 text-amber-500 inline" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </span>
                    @if (!$timelineData['is_open'])
                        <span
                            class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full font-medium">{{ __('dashboard.day_off') }}</span>
                    @else
                        <span class="text-xs text-gray-500">{{ $timelineData['start_time'] }} -
                            {{ $timelineData['end_time'] }}</span>
                    @endif
                </div>
            </div>

            {{-- Timeline --}}
            @if ($timelineData['is_open'] && count($timelineData['providers']) > 0)
                <div
                    class="relative flex-1 overflow-auto"
                    id="timeline-container"
                    @scroll.passive="onTimelineContainerScroll()"
                >
                    <div
                        x-show="dragging"
                        x-cloak
                        class="drag-selection pointer-events-none fixed z-[70] box-border"
                        :style="dragSelectionOverlayStyle()"
                    ></div>
                    <div class="flex min-h-full">
                        {{-- Time Labels Column: flex-col حتى يطابق رأس h-10 + الجدول أعمدة الموظفين --}}
                        <div class="flex w-16 flex-shrink-0 flex-col bg-white border-r border-gray-200 sticky left-0 z-10">
                            <div class="sticky top-0 z-20 h-10 flex-shrink-0 border-b border-gray-200 bg-white"></div>
                            @php
                                $start = \Carbon\Carbon::parse($timelineData['start_time']);
                                $end = \Carbon\Carbon::parse($timelineData['end_time']);
                                $totalMinutes = $start->diffInMinutes($end);
                            @endphp
                            <div class="relative flex-shrink-0" :style="timelineColumnStyle({{ $totalMinutes }})">
                                <template x-for="minute in timelineLabelMinutes({{ $totalMinutes }})"
                                    :key="`label-${minute}`">
                                    <div
                                        class="pointer-events-none absolute inset-x-0 flex justify-start"
                                        :style="timelineLabelTickStyle(minute)"
                                    >
                                        {{-- نفس إحداثي border-top لخلية الشبكة؛ -translate-y-1/2 يضع منتصف النص على الخط --}}
                                        <span
                                            class="inline-block -translate-y-1/2 transform px-2 text-[10px] font-medium leading-none text-gray-500"
                                            x-text="formatTimelineMinute(minute, @js($timelineData['start_time']))"
                                        ></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Provider Columns --}}
                        <div class="flex flex-1">
                            @foreach ($timelineData['providers'] as $pIndex => $provider)
                                <div class="relative flex min-w-[180px] flex-1 flex-col border-r-2 border-gray-200 last:border-r-0">
                                    {{-- Provider Header --}}
                                    <div
                                        class="sticky top-0 z-10 flex h-10 flex-shrink-0 items-center justify-center border-b border-gray-200 bg-white px-2">
                                        <span
                                            class="text-xs font-semibold text-gray-700 truncate">{{ $provider['name'] }}</span>
                                    </div>

                                    {{-- Timeline Grid --}}
                                    <div
                                        class="relative flex-shrink-0 select-none"
                                        :style="timelineColumnStyle({{ $totalMinutes }})"
                                        data-provider-id="{{ $provider['id'] }}"
                                        @mousedown="startDrag($event, {{ $provider['id'] }})"
                                    >
                                        {{-- Grid Lines --}}
                                        <template x-for="minute in timelineGridMinutes({{ $totalMinutes }})"
                                            :key="'p{{ $provider['id'] }}-grid-' + minute">
                                            <div class="absolute left-0 right-0" :class="timelineGridLineClass(minute)"
                                                :style="timelineGridLineStyle(minute)"></div>
                                        </template>

                                        {{-- Current Time Indicator --}}
                                        @if ($selectedDate === $today)
                                            @php
                                                $nowMinutes = \Carbon\Carbon::now()->diffInMinutes($start);
                                            @endphp
                                            @if ($nowMinutes >= 0 && $nowMinutes <= $totalMinutes)
                                                <div class="absolute left-0 right-0 z-30 pointer-events-none"
                                                    :style="timelineMarkerStyle({{ $nowMinutes }})">
                                                    <div class="border-t-2 border-red-500 relative">
                                                        <div
                                                            class="absolute -top-1.5 -left-1 w-3 h-3 bg-red-500 rounded-full">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif

                                        {{-- Time Off Blocks --}}
                                        @if (isset($timelineData['time_offs'][$provider['id']]))
                                            @foreach ($timelineData['time_offs'][$provider['id']] as $timeOff)
                                                @php
                                                    $toStart = \Carbon\Carbon::parse($timeOff['start_time']);
                                                    $toEnd = \Carbon\Carbon::parse($timeOff['end_time']);
                                                    $toOffsetMinutes = max(0, $start->diffInMinutes($toStart, false));
                                                    $toDurationMinutes = $toStart->diffInMinutes($toEnd);
                                                @endphp
                                                <div class="absolute left-1 right-1 time-off-block bg-gray-100 rounded-md border border-gray-200 z-5 flex items-center justify-center"
                                                    :style="timelineBlockStyle({{ $toOffsetMinutes }},
                                                        {{ $toDurationMinutes }})">
                                                    <span
                                                        class="text-[10px] text-gray-500 font-medium rotate-0">{{ $timeOff['reason'] ?: __('dashboard.on_leave') }}</span>
                                                </div>
                                            @endforeach
                                        @endif

                                        {{-- Appointment Cards --}}
                                        @if (isset($timelineData['appointments'][$provider['id']]))
                                            @foreach ($timelineData['appointments'][$provider['id']] as $apt)
                                                @php
                                                    $aptStart = \Carbon\Carbon::parse($apt['start_time']);
                                                    $aptEnd = \Carbon\Carbon::parse($apt['end_time']);
                                                    $aptOffsetMinutes = max(0, $start->diffInMinutes($aptStart, false));
                                                    $aptDurationMinutes = $aptStart->diffInMinutes($aptEnd);

                                                    $statusClass = match ($apt['status']) {
                                                        0 => 'status-pending border-l-yellow-400',
                                                        1 => 'status-completed border-l-green-500',
                                                        -1, -2 => 'status-cancelled border-l-red-500',
                                                        -3 => 'status-no-show border-l-gray-500',
                                                        default => 'status-pending border-l-yellow-400',
                                                    };
                                                    $bgClass = match ($apt['status']) {
                                                        0 => 'bg-amber-50 hover:bg-amber-100',
                                                        1 => 'bg-green-50 hover:bg-green-100',
                                                        -1, -2 => 'bg-red-50 hover:bg-red-100',
                                                        -3 => 'bg-gray-50 hover:bg-gray-100',
                                                        default => 'bg-white hover:bg-gray-50',
                                                    };
                                                @endphp
                                                <div class="appointment-card absolute left-1 right-1 rounded-md border border-gray-200 {{ $bgClass }} {{ $statusClass }} overflow-hidden z-10 px-1.5 py-1"
                                                    :style="timelineBlockStyle({{ $aptOffsetMinutes }},
                                                        {{ $aptDurationMinutes }})"
                                                    wire:click="openAppointmentModal({{ $apt['id'] }})"
                                                    title="{{ $apt['services'] }}">
                                                    <div
                                                        class="text-[10px] font-semibold text-gray-800 leading-tight truncate">
                                                        {{ $apt['start_time'] }} - {{ $apt['end_time'] }}
                                                    </div>
                                                    <div x-show="blockVisible({{ $aptDurationMinutes }}, 20)"
                                                        class="text-[10px] text-gray-600 truncate leading-tight">
                                                        {{ $apt['services'] }}</div>
                                                    <div x-show="blockVisible({{ $aptDurationMinutes }}, 35)"
                                                        class="text-[10px] text-gray-500 truncate leading-tight">
                                                        {{ $apt['has_account'] ? '@' : '' }}{{ $apt['customer_name'] }}
                                                    </div>
                                                    <div x-show="blockVisible({{ $aptDurationMinutes }}, 50)"
                                                        class="text-[9px] text-gray-400 truncate">
                                                        #{{ $apt['number'] }}</div>
                                                    @if (!empty($apt['service_color_code']))
                                                        <div class="absolute inset-x-0 bottom-0 h-1"
                                                            style="background-color: {{ $apt['service_color_code'] }};">
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif(!$timelineData['is_open'])
                <div class="flex-1 flex items-center justify-center">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">{{ __('dashboard.day_off') }}</p>
                    </div>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center">
                    <p class="text-sm text-gray-500">{{ __('dashboard.timeline.no_providers') }}</p>
                </div>
            @endif
        </main>
    </div>

    {{-- ===== MODALS ===== --}}

    {{-- Create Booking Modal (Alpine-controlled) --}}
    <div x-show="showBookingModal" x-cloak
        class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
        @click.self="showBookingModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.booking_modal.title') }}</h3>
                <button @click="showBookingModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                {{-- Customer Type Toggle --}}
                <div class="flex space-x-2">
                    <button @click="booking.customerType = 'existing'"
                        class="flex-1 py-2 text-sm rounded-lg font-medium"
                        :class="booking.customerType === 'existing' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600'">
                        {{ __('dashboard.booking_modal.existing_customer') }}
                    </button>
                    <button @click="booking.customerType = 'guest'" class="flex-1 py-2 text-sm rounded-lg font-medium"
                        :class="booking.customerType === 'guest' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600'">
                        {{ __('dashboard.booking_modal.guest_customer') }}
                    </button>
                </div>

                <template x-if="booking.customerType === 'existing'">
                    <div class="relative">
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_customer') }}</label>
                        <input type="text" x-model="booking.customerSearch"
                            @focus="booking.customerDropdownOpen = true"
                            placeholder="{{ __('dashboard.search') }}..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <div x-show="booking.customerDropdownOpen && filteredCustomers().length > 0"
                            @click.outside="booking.customerDropdownOpen = false"
                            class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                            <template x-for="c in filteredCustomers()" :key="c.id">
                                <button
                                    @click="booking.selectedCustomerId = c.id; booking.customerSearch = c.name; booking.customerDropdownOpen = false"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-amber-50 border-b border-gray-50">
                                    <span x-text="c.name" class="font-medium"></span>
                                    <span x-text="c.phone" class="text-gray-400 ml-2 text-xs"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="booking.customerType === 'guest'">
                    <div class="space-y-3">
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.customer_name') }}
                                *</label>
                            <input type="text" x-model="booking.guestName"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.customer_phone') }}
                                *</label>
                            <input type="text" x-model="booking.guestPhone"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.customer_email') }}</label>
                            <input type="email" x-model="booking.guestEmail"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                </template>

                <hr class="border-gray-100">

                {{-- Services --}}
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                        {{ __('dashboard.booking_modal.services') }}</h4>
                    <template x-for="(bs, bIndex) in booking.services" :key="bIndex">
                        <div class="bg-gray-50 rounded-lg p-3 mb-3 relative">
                            <button x-show="booking.services.length > 1" @click="booking.services.splice(bIndex, 1)"
                                class="absolute top-2 right-2 text-red-400 hover:text-red-600 text-xs">
                                {{ __('dashboard.booking_modal.remove_service') }}
                            </button>
                            <div class="space-y-3">
                                {{-- Category --}}
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_category') }}</label>
                                    <select x-model="bs.category_id"
                                        @change="bs.service_id = ''; bs.provider_id = ''; bs.duration = 0; bs.price = 0"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">-- {{ __('dashboard.booking_modal.select_category') }}
                                            --</option>
                                        <template x-for="cat in preloaded.categories" :key="cat.id">
                                            <option :value="cat.id" x-text="cat.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Service --}}
                                <div x-show="bs.category_id">
                                    <label
                                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_service') }}</label>
                                    <select x-model="bs.service_id" @change="onServiceChange(bs)"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">-- {{ __('dashboard.booking_modal.select_service') }}
                                            --</option>
                                        <template x-for="s in servicesForCategory(bs.category_id)"
                                            :key="s.id">
                                            <option :value="s.id"
                                                x-text="s.name + ' (' + s.duration_minutes + ' min - €' + (s.discount_price || s.price) + ')'">
                                            </option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Time & Duration --}}
                                <div x-show="bs.service_id" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.start_time') }}</label>
                                        <input type="time" x-model="bs.start_time" :step="timelineScale * 60"
                                            @change="bs.start_time = snapTime(bs.start_time); bs.provider_id = ''; loadProviders(bIndex)"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.duration') }}</label>
                                        <input type="number" x-model.number="bs.duration"
                                            @change="if (bs.service_id && bs.start_time) { bs.provider_id = ''; loadProviders(bIndex); }"
                                            min="5" step="5"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                </div>
                                {{-- Available Providers (from server) --}}
                                <div x-show="bs.service_id && bs.start_time">
                                    <label
                                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_provider') }}</label>
                                    <template x-if="bs._loadingProviders">
                                        <p class="text-xs text-gray-400">{{ __('dashboard.loading') }}...</p>
                                    </template>
                                    <div class="space-y-1" x-show="!bs._loadingProviders">
                                        <template x-for="p in (bs._availableProviders || [])" :key="p.id">
                                            <label
                                                class="flex items-center space-x-2 px-3 py-2 rounded-lg border cursor-pointer hover:bg-amber-50"
                                                :class="bs.provider_id == p.id ? 'border-amber-500 bg-amber-50' :
                                                    'border-gray-200'"
                                                @click="bs.provider_id = p.id">
                                                <span
                                                    class="w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                                    :class="bs.provider_id == p.id ? 'border-amber-500' : 'border-gray-300'">
                                                    <span x-show="bs.provider_id == p.id"
                                                        class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                </span>
                                                <span class="text-sm" x-text="p.name"></span>
                                            </label>
                                        </template>
                                        <template
                                            x-if="!bs._loadingProviders && (!bs._availableProviders || bs._availableProviders.length === 0)">
                                            <p class="text-xs text-gray-400">
                                                {{ __('dashboard.booking_modal.no_providers') }}</p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <button @click="addBookingService()"
                        class="text-xs text-amber-600 hover:text-amber-700 font-medium">+
                        {{ __('dashboard.booking_modal.add_service') }}</button>
                </div>

                {{-- Notes --}}
                <div>
                    <label
                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.notes') }}</label>
                    <textarea x-model="booking.notes" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500"></textarea>
                </div>
            </div>
            <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                <button @click="showBookingModal = false"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.booking_modal.cancel') }}</button>
                <button @click="submitBooking()" :disabled="bookingSaving"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                    <span x-show="!bookingSaving">{{ __('dashboard.booking_modal.save') }}</span>
                    <span x-show="bookingSaving">...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Appointment Detail Modal --}}
    @if ($showAppointmentModal && $selectedAppointment)
        <div class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
            wire:click.self="closeAppointmentModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.appointment_modal.title') }}</h3>
                    <button wire:click="closeAppointmentModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    @php
                        $statusBadge = match ($selectedAppointment->status->value) {
                            0 => [
                                'wrapper' => 'bg-amber-100 text-amber-800',
                                'dot' => 'bg-amber-500',
                            ],
                            1 => [
                                'wrapper' => 'bg-green-100 text-green-800',
                                'dot' => 'bg-green-500',
                            ],
                            -1, -2 => [
                                'wrapper' => 'bg-red-100 text-red-800',
                                'dot' => 'bg-red-500',
                            ],
                            -3 => [
                                'wrapper' => 'bg-slate-200 text-slate-700',
                                'dot' => 'bg-slate-500',
                            ],
                            default => [
                                'wrapper' => 'bg-gray-100 text-gray-700',
                                'dot' => 'bg-gray-400',
                            ],
                        };

                        $paymentBadge = match ($selectedAppointment->payment_status->value) {
                            0 => [
                                'wrapper' => 'bg-amber-100 text-amber-800',
                                'dot' => 'bg-amber-500',
                            ],
                            1, 2, 3 => [
                                'wrapper' => 'bg-green-100 text-green-800',
                                'dot' => 'bg-green-500',
                            ],
                            4 => [
                                'wrapper' => 'bg-red-100 text-red-800',
                                'dot' => 'bg-red-500',
                            ],
                            5, 6 => [
                                'wrapper' => 'bg-sky-100 text-sky-800',
                                'dot' => 'bg-sky-500',
                            ],
                            default => [
                                'wrapper' => 'bg-gray-100 text-gray-700',
                                'dot' => 'bg-gray-400',
                            ],
                        };
                    @endphp
                    {{-- Appointment Info --}}
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.booking_number') }}</span>
                            <span class="font-medium">#{{ $selectedAppointment->number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.customer') }}</span>
                            <span class="font-medium">
                                @if ($selectedAppointment->customer_id)
                                    <span class="text-amber-600">@</span>
                                @endif
                                {{ $selectedAppointment->customer?->full_name ?: $selectedAppointment->getRawOriginal('customer_name') ?: 'Guest' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.service') }}</span>
                            <span
                                class="font-medium">{{ $selectedAppointment->services_record->map(fn($s) => $s->service_name)->implode(', ') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.provider') }}</span>
                            <span class="font-medium">{{ $selectedAppointment->provider?->full_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.status') }}</span>
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge['wrapper'] }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $statusBadge['dot'] }}"></span>
                                <span>{{ $selectedAppointment->status->getLabel() }}</span>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.payment_status') }}</span>
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentBadge['wrapper'] }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $paymentBadge['dot'] }}"></span>
                                <span>{{ $selectedAppointment->payment_status->label() }}</span>
                            </span>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- Edit Time --}}
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">
                            {{ __('dashboard.appointment_modal.edit_time') }}</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs text-gray-500 mb-1">{{ __('dashboard.appointment_modal.new_start_time') }}</label>
                                <input type="time" wire:model="editStartTime" :step="timelineScale * 60"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label
                                    class="block text-xs text-gray-500 mb-1">{{ __('dashboard.appointment_modal.new_duration') }}</label>
                                <input type="number" wire:model="editDuration" min="5" step="5"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex items-center justify-between">
                    <div class="flex space-x-2">
                        @if (!in_array($selectedAppointment->payment_status->value, [1, 2, 3]) && $selectedAppointment->status->value !== 1)
                            <button wire:click="cancelAppointment"
                                wire:confirm="{{ __('dashboard.appointment_modal.confirm_cancel') }}"
                                class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">{{ __('dashboard.appointment_modal.cancel_appointment') }}</button>
                            <button wire:click="deleteAppointment"
                                wire:confirm="{{ __('dashboard.appointment_modal.confirm_delete') }}"
                                class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">{{ __('dashboard.appointment_modal.delete') }}</button>
                        @endif
                    </div>
                    <div class="flex space-x-2">
                        @if ($selectedAppointment->status->value === 0)
                            <button wire:click="openPaymentModal({{ $selectedAppointment->id }})"
                                class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg">
                                {{ __('dashboard.appointment_modal.pay') }}
                            </button>
                        @endif
                        <button wire:click="updateAppointment"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                            {{ __('dashboard.appointment_modal.save_changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Payment Modal --}}
    @if ($showPaymentModal && $selectedAppointmentId)
        @php $payApt = $selectedAppointment ?? \App\Models\Appointment::with('services_record')->find($selectedAppointmentId); @endphp
        <div class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
            wire:click.self="closePaymentModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.payment_modal.title') }}</h3>
                    <button wire:click="closePaymentModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    @if ($payApt)
                        <div class="text-sm text-gray-500">
                            {{ $payApt->services_record->map(fn($s) => $s->service_name)->implode(', ') }}
                        </div>
                    @endif
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.payment_modal.amount_to_pay') }}</label>
                        <input type="number" wire:model="paymentAmount" step="0.01" min="0"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-lg font-semibold text-center focus:ring-amber-500 focus:border-amber-500">
                        <p class="text-[10px] text-gray-400 mt-1">{{ __('dashboard.payment_modal.discount_note') }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-2">{{ __('dashboard.payment_modal.payment_type') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex items-center justify-center px-4 py-3 rounded-lg border cursor-pointer {{ $paymentType === '2' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                <input type="radio" wire:model="paymentType" value="2" class="sr-only">
                                <span class="text-sm font-medium">{{ __('dashboard.payment_modal.cash') }}</span>
                            </label>
                            <label
                                class="flex items-center justify-center px-4 py-3 rounded-lg border cursor-pointer {{ $paymentType === '3' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                <input type="radio" wire:model="paymentType" value="3" class="sr-only">
                                <span class="text-sm font-medium">{{ __('dashboard.payment_modal.card') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                    <button wire:click="closePaymentModal"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.payment_modal.cancel') }}</button>
                    <button wire:click="processPayment"
                        class="px-5 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove
                            wire:target="processPayment">{{ __('dashboard.payment_modal.confirm_pay') }}</span>
                        <span wire:loading wire:target="processPayment">...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Time Off Modal --}}
    @if ($showTimeOffModal)
        <div class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
            wire:click.self="closeTimeOffModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.time_off_modal.title') }}</h3>
                    <button wire:click="closeTimeOffModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.select_provider') }}</label>
                        <select wire:model="timeOffProviderId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="">-- {{ __('dashboard.time_off_modal.select_provider') }} --
                            </option>
                            @foreach ($allProviders as $p)
                                <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-2">{{ __('dashboard.time_off_modal.type') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex items-center justify-center px-3 py-2 rounded-lg border cursor-pointer {{ $timeOffType === '1' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                <input type="radio" wire:model.live="timeOffType" value="1" class="sr-only">
                                <span
                                    class="text-sm font-medium">{{ __('dashboard.time_off_modal.full_day') }}</span>
                            </label>
                            <label
                                class="flex items-center justify-center px-3 py-2 rounded-lg border cursor-pointer {{ $timeOffType === '0' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                <input type="radio" wire:model.live="timeOffType" value="0" class="sr-only">
                                <span class="text-sm font-medium">{{ __('dashboard.time_off_modal.hourly') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.start_date') }}</label>
                            <input type="date" wire:model="timeOffStartDate"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.end_date') }}</label>
                            <input type="date" wire:model="timeOffEndDate"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    @if ($timeOffType === '0')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.start_time') }}</label>
                                <input type="time" wire:model="timeOffStartTime"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.end_time') }}</label>
                                <input type="time" wire:model="timeOffEndTime"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                        </div>
                    @endif
                    @if ($this->reasonLeaves && $this->reasonLeaves->count() > 0)
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.reason') }}</label>
                            <select wire:model="timeOffReasonId"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                <option value="">-- {{ __('dashboard.time_off_modal.reason') }} --</option>
                                @foreach ($this->reasonLeaves as $reason)
                                    <option value="{{ $reason->id }}">
                                        {{ $reason->getNameIn(app()->getLocale()) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                    <button wire:click="closeTimeOffModal"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.time_off_modal.cancel') }}</button>
                    <button wire:click="saveTimeOff"
                        class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                        {{ __('dashboard.time_off_modal.save') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <script>
        function dashboardApp() {
            return {
                _pollTimer: null,
                dragging: false,
                dragProviderId: null,
                dragStartY: 0,
                dragCurrentY: 0,
                dragStartOffset: 0,
                _dragTimelineEl: null,
                _onDocDragMove: null,
                _onDocDragUp: null,
                _dragLayoutVersion: 0,

                showBookingModal: false,
                bookingSaving: false,
                timelineScaleStorageKey: 'staff-dashboard-timeline-scale',
                timelineBaseSlotHeight: 45,
                timelineScaleOptions: [{
                        value: 15,
                        label: @js(__('dashboard.timeline.scale_15'))
                    },
                    {
                        value: 30,
                        label: @js(__('dashboard.timeline.scale_30'))
                    },
                    {
                        value: 10,
                        label: @js(__('dashboard.timeline.scale_10'))
                    },
                ],
                timelineScale: 15,
                preloaded: window.__dashboardPreloaded || @json($preloadedData),
                booking: {
                    customerType: 'existing',
                    selectedCustomerId: null,
                    customerSearch: '',
                    customerDropdownOpen: false,
                    guestName: '',
                    guestPhone: '',
                    guestEmail: '',
                    services: [{
                        category_id: '',
                        service_id: '',
                        start_time: '',
                        duration: 0,
                        provider_id: '',
                        price: 0,
                        _availableProviders: [],
                        _loadingProviders: false
                    }],
                    notes: '',
                },

                resetBooking() {
                    this.booking = {
                        customerType: 'existing',
                        selectedCustomerId: null,
                        customerSearch: '',
                        customerDropdownOpen: false,
                        guestName: '',
                        guestPhone: '',
                        guestEmail: '',
                        services: [{
                            category_id: '',
                            service_id: '',
                            start_time: '',
                            duration: 0,
                            provider_id: '',
                            price: 0,
                            _availableProviders: [],
                            _loadingProviders: false
                        }],
                        notes: '',
                    };
                    this.bookingSaving = false;
                },

                init() {
                    const savedScale = parseInt(window.localStorage.getItem(this.timelineScaleStorageKey), 10);
                    if (this.timelineScaleOptions.some(option => option.value === savedScale)) {
                        this.timelineScale = savedScale;
                    }
                },

                setTimelineScale(value) {
                    const normalizedValue = parseInt(value, 10);
                    if (!this.timelineScaleOptions.some(option => option.value === normalizedValue)) {
                        return;
                    }

                    this.timelineScale = normalizedValue;
                    window.localStorage.setItem(this.timelineScaleStorageKey, String(normalizedValue));
                    this.booking.services = this.booking.services.map(service => ({
                        ...service,
                        start_time: service.start_time ? this.snapTime(service.start_time) : service.start_time,
                        provider_id: service.start_time ? '' : service.provider_id,
                        _availableProviders: service.start_time ? [] : (service._availableProviders || []),
                    }));
                },

                timelineScaleLabel() {
                    return this.timelineScaleOptions.find(option => option.value === this.timelineScale)?.label || '';
                },

                pixelsPerMinute() {
                    return this.timelineBaseSlotHeight / this.timelineScale;
                },

                minuteToPixels(minutes) {
                    return minutes * this.pixelsPerMinute();
                },

                timelineColumnStyle(totalMinutes) {
                    return `height: ${this.minuteToPixels(totalMinutes)}px;`;
                },

                /** محاذاة تسمية الوقت مع خط الشبكة (نفس top مثل timelineGridLineStyle) */
                timelineLabelTickStyle(minute) {
                    const y = this.minuteToPixels(minute);
                    return `top:${y}px;`;
                },

                timelineGridLineStyle(minute) {
                    return `top: ${this.minuteToPixels(minute)}px; height: ${this.timelineBaseSlotHeight}px;`;
                },

                timelineMarkerStyle(offsetMinutes) {
                    return `top: ${this.minuteToPixels(offsetMinutes)}px;`;
                },

                timelineBlockStyle(offsetMinutes, durationMinutes) {
                    return `top: ${this.minuteToPixels(offsetMinutes)}px; height: ${this.minuteToPixels(durationMinutes)}px;`;
                },

                blockVisible(durationMinutes, thresholdPixels) {
                    return this.minuteToPixels(durationMinutes) > thresholdPixels;
                },

                timelineGridMinutes(totalMinutes) {
                    return Array.from({
                            length: Math.ceil(totalMinutes / this.timelineScale)
                        }, (_, index) => index * this.timelineScale)
                        .filter(minute => minute < totalMinutes);
                },

                timelineLabelMinutes(totalMinutes) {
                    return Array.from({
                            length: Math.ceil(totalMinutes / this.timelineScale)
                        }, (_, index) => index * this.timelineScale)
                        .filter(minute => minute < totalMinutes);
                },

                timelineGridLineClass(minute) {
                    if (minute % 60 === 0) {
                        return 'timeline-grid-line-hour';
                    }

                    return minute % 30 === 0 ? 'timeline-grid-line' : 'border-t border-gray-100';
                },

                formatTimelineMinute(offsetMinutes, startTime) {
                    const [startHours, startMinutes] = startTime.split(':').map(Number);
                    const totalMinutes = (startHours * 60) + startMinutes + offsetMinutes;
                    const hours = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
                    const minutes = String(totalMinutes % 60).padStart(2, '0');

                    return `${hours}:${minutes}`;
                },

                snapTime(time) {
                    if (!time) {
                        return time;
                    }

                    const [hours, minutes] = time.split(':').map(Number);
                    const totalMinutes = (hours * 60) + minutes;
                    const snappedMinutes = Math.round(totalMinutes / this.timelineScale) * this.timelineScale;
                    const normalizedHours = Math.floor((snappedMinutes % (24 * 60)) / 60);
                    const normalizedMinutes = snappedMinutes % 60;

                    return `${String(normalizedHours).padStart(2, '0')}:${String(normalizedMinutes).padStart(2, '0')}`;
                },

                openBookingModalLocal(providerId = null, startTime = null) {
                    this.resetBooking();
                    if (startTime) {
                        this.booking.services[0].start_time = this.snapTime(startTime);
                    } else {
                        const now = new Date();
                        const currentTime =
                            `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
                        this.booking.services[0].start_time = this.snapTime(currentTime);
                    }
                    if (providerId) {
                        this.booking.services[0]._preselectedProvider = providerId;
                    }
                    this.showBookingModal = true;
                },

                filteredCustomers() {
                    const q = (this.booking.customerSearch || '').toLowerCase().trim();
                    if (!q) return this.preloaded.customers.slice(0, 20);
                    return this.preloaded.customers.filter(c =>
                        (c.name && c.name.toLowerCase().includes(q)) ||
                        (c.phone && c.phone.includes(q)) ||
                        (c.email && c.email.toLowerCase().includes(q))
                    ).slice(0, 20);
                },

                servicesForCategory(categoryId) {
                    if (!categoryId) return [];
                    const cid = parseInt(categoryId);
                    return this.preloaded.services.filter(s => s.category_id === cid);
                },

                onServiceChange(bs) {
                    const service = this.preloaded.services.find(s => s.id == bs.service_id);
                    if (service) {
                        bs.duration = service.duration_minutes;
                        bs.price = service.discount_price || service.price;
                    } else {
                        bs.duration = 0;
                        bs.price = 0;
                    }
                    bs.provider_id = '';
                    bs._availableProviders = [];
                    if (bs.start_time && bs.service_id) {
                        this.loadProvidersForService(bs);
                    }
                },

                addBookingService() {
                    const lastService = this.booking.services[this.booking.services.length - 1];
                    let nextTime = '';
                    if (lastService && lastService.start_time && lastService.duration) {
                        const [h, m] = lastService.start_time.split(':').map(Number);
                        const totalMin = h * 60 + m + (lastService.duration || 30);
                        nextTime = this.snapTime(String(Math.floor(totalMin / 60)).padStart(2, '0') + ':' + String(
                            totalMin % 60).padStart(2, '0'));
                    }
                    this.booking.services.push({
                        category_id: '',
                        service_id: '',
                        start_time: nextTime,
                        duration: 0,
                        provider_id: '',
                        price: 0,
                        _availableProviders: [],
                        _loadingProviders: false
                    });
                },

                async loadProviders(bIndex) {
                    const bs = this.booking.services[bIndex];
                    if (!bs || !bs.service_id || !bs.start_time) return;
                    await this.loadProvidersForService(bs);
                },

                async loadProvidersForService(bs) {
                    if (!bs.service_id || !bs.start_time) return;
                    bs._loadingProviders = true;
                    bs._availableProviders = [];
                    try {
                        const result = await this.$wire.getAvailableProvidersForBooking(
                            parseInt(bs.service_id),
                            bs.start_time,
                            bs.duration || 30
                        );
                        bs._availableProviders = result;
                        if (bs._preselectedProvider && result.some(p => p.id == bs._preselectedProvider)) {
                            bs.provider_id = bs._preselectedProvider;
                            delete bs._preselectedProvider;
                        }
                    } catch (e) {
                        bs._availableProviders = [];
                    }
                    bs._loadingProviders = false;
                },

                async submitBooking() {
                    this.bookingSaving = true;
                    const data = {
                        customerType: this.booking.customerType,
                        selectedCustomerId: this.booking.selectedCustomerId,
                        guestName: this.booking.guestName,
                        guestPhone: this.booking.guestPhone,
                        guestEmail: this.booking.guestEmail,
                        notes: this.booking.notes,
                        services: this.booking.services.map(s => ({
                            category_id: s.category_id,
                            service_id: s.service_id,
                            start_time: s.start_time,
                            duration: s.duration,
                            provider_id: s.provider_id,
                            price: s.price,
                        })),
                    };
                    try {
                        await this.$wire.saveBookingFromAlpine(data);
                        this.showBookingModal = false;
                        this.resetBooking();
                    } catch (e) {
                        this.bookingSaving = false;
                    }
                },

                detachDocumentDragListeners() {
                    if (this._onDocDragMove) {
                        document.removeEventListener('mousemove', this._onDocDragMove);
                        this._onDocDragMove = null;
                    }
                    if (this._onDocDragUp) {
                        document.removeEventListener('mouseup', this._onDocDragUp);
                        this._onDocDragUp = null;
                    }
                },

                onTimelineContainerScroll() {
                    if (this.dragging) {
                        this._dragLayoutVersion++;
                    }
                },

                dragSelectionOverlayStyle() {
                    if (!this.dragging || !this._dragTimelineEl) {
                        return 'display:none;';
                    }
                    void this._dragLayoutVersion;
                    void this.dragStartY;
                    void this.dragCurrentY;
                    const col = this._dragTimelineEl.getBoundingClientRect();
                    const pad = 4;
                    const topRel = Math.min(this.dragStartY, this.dragCurrentY);
                    const h = Math.max(Math.abs(this.dragCurrentY - this.dragStartY), 3);
                    const left = col.left + pad;
                    const top = col.top + topRel;
                    const width = Math.max(col.width - pad * 2, 0);
                    return `position:fixed;left:${left}px;top:${top}px;width:${width}px;height:${h}px;`;
                },

                startDrag(e, providerId) {
                    if (e.target.closest('.appointment-card')) return;
                    if (e.target.closest('.time-off-block')) return;
                    const el = e.currentTarget;
                    if (!el) return;
                    e.preventDefault();
                    this.detachDocumentDragListeners();
                    this._dragTimelineEl = el;
                    const rect = el.getBoundingClientRect();
                    this.dragging = true;
                    this.dragProviderId = Number(providerId);
                    this.dragStartY = e.clientY - rect.top;
                    this.dragCurrentY = this.dragStartY;

                    this._onDocDragMove = (ev) => this.onDocumentDragMove(ev);
                    this._onDocDragUp = () => this.finishDragFromDocument();
                    document.addEventListener('mousemove', this._onDocDragMove, { passive: true });
                    document.addEventListener('mouseup', this._onDocDragUp);
                },

                onDocumentDragMove(e) {
                    if (!this.dragging || !this._dragTimelineEl) return;
                    const rect = this._dragTimelineEl.getBoundingClientRect();
                    let y = e.clientY - rect.top;
                    y = Math.max(0, Math.min(rect.height, y));
                    this.dragCurrentY = y;
                },

                finishDragFromDocument() {
                    this.detachDocumentDragListeners();
                    const providerId = this.dragProviderId;
                    const timelineEl = this._dragTimelineEl;
                    this._dragTimelineEl = null;

                    if (!this.dragging || providerId == null || !timelineEl) {
                        this.dragging = false;
                        this.dragProviderId = null;
                        return;
                    }

                    const diff = Math.abs(this.dragCurrentY - this.dragStartY);
                    const isClick = diff < 10;

                    const pixelsPerMinute = this.pixelsPerMinute();
                    const topY = isClick ? this.dragStartY : Math.min(this.dragStartY, this.dragCurrentY);
                    const minutesFromStart = Math.round(topY / pixelsPerMinute / this.timelineScale) * this.timelineScale;

                    const timelineStart = @json($timelineData['start_time'] ?? '09:00');
                    const [startH, startM] = timelineStart.split(':').map(Number);
                    const totalStartMinutes = startH * 60 + startM + minutesFromStart;
                    const hours = String(Math.floor(totalStartMinutes / 60)).padStart(2, '0');
                    const mins = String(totalStartMinutes % 60).padStart(2, '0');
                    const startTime = hours + ':' + mins;

                    this.dragging = false;
                    this.dragProviderId = null;

                    this.openBookingModalLocal(providerId, startTime);
                },
            }
        }
    </script>
</div>
