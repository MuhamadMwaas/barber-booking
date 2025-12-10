{{--
    Salon Schedule Manager View
    ====================================
    عرض إدارة جدول مواعيد الصالون مع Timeline احترافي
--}}

<div class="salon-schedule-manager"
     x-data="{
        confirmReset: false,
        showCopyModal: false
     }"
     dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header Section --}}
    <div class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {{-- Branch Selection --}}
            <div class="flex-1 max-w-md">
                <label for="branch-select" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('salon_schedule.select_branch') }}
                </label>
                <select
                    id="branch-select"
                    wire:model.live="selectedBranchId"
                    class="w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">{{ __('salon_schedule.choose_branch') }}</option>
                    @foreach($this->branches as $branch)
                        <option value="{{ $branch->id }}">
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Quick Actions --}}
            @if($selectedBranchId)
                <div class="flex flex-wrap gap-2">
                    {{-- Reset --}}
                    <button
                        type="button"
                        @click="confirmReset = true"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-orange-700 bg-orange-100 rounded-lg hover:bg-orange-200"
                        title="{{ __('salon_schedule.reload') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span class="hidden sm:inline">{{ __('salon_schedule.reload') }}</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- Stats Bar --}}
        @if($selectedBranchId)
            <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ __('salon_schedule.total_weekly_hours') }}: <strong>{{ $this->totalWeeklyHours }}</strong> {{ __('salon_schedule.working_hours') }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ __('salon_schedule.open_days_count') }}: <strong>{{ $this->openDaysCount }}</strong> {{ __('salon_schedule.days_unit') }}</span>
                </div>

                @if($hasUnsavedChanges)
                    <div class="flex items-center gap-2 text-amber-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>{{ __('schedule.unsaved_changes') }}</span>
                    </div>
                @endif

                @if($clipboardType)
                    <div class="flex items-center gap-2 text-green-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span>{{ __('schedule.clipboard_has_day') }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Messages --}}
    @if($successMessage)
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200">
            <div class="flex items-center gap-2 text-green-800">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif

    @if(!empty($errors))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200">
            <div class="flex items-start gap-2 text-red-800">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach($errors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Week Schedule with Timeline --}}
    @if($selectedBranchId)
        <div class="space-y-2">
            @foreach($this->days as $dayNum => $day)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    {{-- Day Header --}}
                    <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                {{-- Day Name --}}
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-sm text-gray-900">
                                        {{ $day['localized'] }}
                                    </h3>
                                    <span class="text-xs text-gray-500">
                                        @if($weeklySchedule[$dayNum]['is_open'])
                                            ({{ $this->getDayHours($dayNum) }}h)
                                        @else
                                            <span class="text-red-500">({{ __('salon_schedule.closed') }})</span>
                                        @endif
                                    </span>
                                </div>

                                {{-- Open/Close Toggle --}}
                                <button
                                    wire:click="toggleDayOpen({{ $dayNum }})"
                                    class="relative inline-flex h-5 w-10 items-center rounded-full transition-colors {{ $weeklySchedule[$dayNum]['is_open'] ? 'bg-green-500' : 'bg-gray-300' }}"
                                >
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $weeklySchedule[$dayNum]['is_open'] ? (app()->getLocale() === 'ar' ? '-translate-x-5' : 'translate-x-5') : (app()->getLocale() === 'ar' ? '-translate-x-0.5' : 'translate-x-0.5') }}"></span>
                                </button>
                            </div>

                            {{-- Day Actions Dropdown --}}
                            <div x-data="{ open: false }" class="relative">
                                <button
                                    @click="open = !open"
                                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                    </svg>
                                </button>

                                <div
                                    x-show="open"
                                    @click.away="open = false"
                                    x-transition
                                    class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-1 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-10"
                                    style="display: none;"
                                >
                                    <button
                                        wire:click="copyDay({{ $dayNum }})"
                                        @click="open = false"
                                        class="w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} flex items-center gap-2"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        {{ __('schedule.copy_day') }}
                                    </button>

                                    @if($clipboardType === 'day')
                                        <button
                                            wire:click="pasteDay({{ $dayNum }})"
                                            @click="open = false"
                                            class="w-full px-4 py-2 text-sm text-blue-600 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} flex items-center gap-2"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            {{ __('schedule.paste_day') }}
                                        </button>
                                    @endif

                                    <button
                                        wire:click="applyDayToAllWeek({{ $dayNum }})"
                                        @click="open = false"
                                        class="w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} flex items-center gap-2"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        {{ __('schedule.apply_to_all_days') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Timeline & Time Inputs --}}
                    <div class="p-3">
                        @if($weeklySchedule[$dayNum]['is_open'])
                            {{-- Time Inputs --}}
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                        {{ __('salon_schedule.open_time') }}
                                    </label>
                                    {{-- <input
                                        type="time"
                                        wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.open_time"
                                        class="w-full text-sm font-semibold rounded border-gray-300 bg-white text-gray-900 focus:border-primary-500 focus:ring-primary-500"
                                    > --}}
                                    <input
                                    type="time"
                                    wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.open_time"
                                    class="w-full h-10 px-3 text-sm font-medium bg-gray-50 border border-gray-300 rounded-lg
                                        focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                        hover:border-gray-400 transition-all ease-in-out duration-150
                                        dark:bg-white dark:text-gray-900"
                                >
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                        {{ __('salon_schedule.close_time') }}
                                    </label>
                                    {{-- <input
                                        type="time"
                                        wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.close_time"
                                        class="w-full text-sm font-semibold rounded border-gray-300 bg-white text-gray-900 focus:border-primary-500 focus:ring-primary-500"
                                    > --}}
                                            <input
                                    type="time"
                                    wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.close_time"
                                    class="w-full h-10 px-3 text-sm font-medium bg-gray-50 border border-gray-300 rounded-lg
                                        focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                        hover:border-gray-400 transition-all ease-in-out duration-150
                                        dark:bg-white dark:text-gray-900"
                                >
                                </div>
                            </div>

                            {{-- Professional Timeline (Grid-based) --}}
                            <div class="relative">
                                @php
                                    // Create 48 time slots (30-minute intervals)
                                    $timeSlots = [];
                                    for ($h = 0; $h < 24; $h++) {
                                        $timeSlots[] = sprintf('%02d:00', $h);
                                        $timeSlots[] = sprintf('%02d:30', $h);
                                    }
                                    $slotCount = count($timeSlots);

                                    // Calculate working hours position
                                    $openTime = $weeklySchedule[$dayNum]['open_time'] ?? '09:00';
                                    $closeTime = $weeklySchedule[$dayNum]['close_time'] ?? '21:00';

                                    // Convert times to slot indices
                                    list($openHour, $openMin) = explode(':', $openTime);
                                    list($closeHour, $closeMin) = explode(':', $closeTime);

                                    $startSlot = (int)$openHour * 2 + ((int)$openMin >= 30 ? 1 : 0);
                                    $endSlot = (int)$closeHour * 2 + ((int)$closeMin >= 30 ? 1 : 0);
                                    $spanSlots = max(1, $endSlot - $startSlot);
                                @endphp

                                {{-- Time ruler (hour labels) --}}
                                <div class="mb-1 grid gap-1 text-[10px] text-gray-500"
                                     style="grid-template-columns: repeat({{ $slotCount }}, minmax(0,1fr));">
                                    @foreach($timeSlots as $index => $slot)
                                        @if($index % 4 === 0)
                                            <div class="text-center font-medium">{{ substr($slot, 0, 5) }}</div>
                                        @else
                                            <div></div>
                                        @endif
                                    @endforeach
                                </div>

                                {{-- Timeline bar with grid --}}
                                <div class="relative overflow-hidden rounded border border-gray-200 bg-gray-50">
                                    {{-- Background grid --}}
                                    <div class="grid gap-px"
                                         style="grid-template-columns: repeat({{ $slotCount }}, minmax(0,1fr));">
                                        @foreach($timeSlots as $i => $slot)
                                            <div class="h-8 @if($i % 2 === 0) bg-gray-50 @else bg-white @endif"></div>
                                        @endforeach
                                    </div>

                                    {{-- Working hours overlay --}}
                                    <div class="absolute inset-0 flex items-center">
                                        <div class="w-full grid"
                                             style="grid-template-columns: repeat({{ $slotCount }}, minmax(0,1fr));">
                                            <div class="relative h-8" style="grid-column: {{ $startSlot + 1 }} / span {{ $spanSlots }};">
                                                <div class="absolute inset-0 rounded border border-emerald-600 bg-emerald-500 text-white flex items-center justify-center">
                                                    <span class="font-semibold text-xs">{{ $openTime }} - {{ $closeTime }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Closed Day Display --}}
                            <div class="flex items-center justify-center py-4 text-gray-400">
                                <svg class="w-8 h-8 mr-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                <span class="text-sm font-medium">{{ __('salon_schedule.closed') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Save Button --}}
        <div class="mt-4 flex items-center justify-end gap-2 sticky bottom-2 bg-white p-3 rounded-lg shadow-lg border border-gray-200">
            <button
                wire:click="resetSchedule"
                type="button"
                class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition-all"
            >
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    {{ __('salon_schedule.reload') }}
                </div>
            </button>

            <button
                wire:click="saveAll"
                type="button"
                class="px-6 py-2 text-sm font-bold text-white bg-emerald-600 rounded hover:bg-emerald-700 shadow-lg hover:shadow-xl transition-all flex items-center gap-2"
                wire:loading.attr="disabled"
                wire:target="saveAll"
            >
                <span wire:loading.remove wire:target="saveAll">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>

                <span wire:loading wire:target="saveAll">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                </span>

                {{ __('salon_schedule.save_schedule') }}
            </button>
        </div>
    @else
        {{-- No Branch Selected --}}
        <div class="flex flex-col items-center justify-center py-20 text-gray-400">
            <div class="relative mb-6">
                <div class="w-24 h-24 bg-gradient-to-br from-primary-100 to-primary-200 rounded-full flex items-center justify-center">
                    <svg class="w-12 h-12 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <p class="text-xl font-semibold mb-2 text-gray-600">{{ __('salon_schedule.select_branch') }}</p>
            <p class="text-sm text-gray-500">{{ __('salon_schedule.select_branch_description') }}</p>
        </div>
    @endif

    {{-- Reset Confirmation Modal --}}
    <div
        x-show="confirmReset"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        @keydown.escape.window="confirmReset = false"
    >
        <div
            class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6"
            @click.away="confirmReset = false"
        >
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ __('salon_schedule.reload') }}?
                </h3>
            </div>
            <p class="text-gray-600 mb-6">
                {{ __('schedule.confirm_reset_message') }}
            </p>
            <div class="flex justify-end gap-3">
                <button
                    @click="confirmReset = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                    {{ __('schedule.cancel') }}
                </button>
                <button
                    @click="confirmReset = false; $wire.resetSchedule()"
                    class="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700"
                >
                    {{ __('salon_schedule.reload') }}
                </button>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        .salon-schedule-manager {
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</div>
