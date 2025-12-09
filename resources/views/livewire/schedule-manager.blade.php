{{--
    Schedule Manager View - Light Theme
    ====================================
    عرض إدارة جدول الشفتات الأسبوعي
    ثيم فاتح فقط
--}}

<div class="schedule-manager"
     x-data="{
        confirmReset: false,
        confirmClear: false,
        showCopyFromModal: false,
        copyFromUserId: null
     }"
     dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header Section --}}
    <div class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {{-- Provider Selection --}}
            <div class="flex-1 max-w-md">
                <label for="provider-select" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('schedule.select_provider') }}
                </label>
                <select
                    id="provider-select"
                    wire:model.live="selectedUserId"
                    class="w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">{{ __('schedule.choose_provider') }}</option>
                    @foreach($this->providers as $provider)
                        <option value="{{ $provider->id }}">
                            {{ $provider->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Quick Actions --}}
            @if($selectedUserId)
                <div class="flex flex-wrap gap-2">
                    {{-- Copy Week --}}
                    <button
                        type="button"
                        wire:click="copyWeek"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        title="{{ __('schedule.copy_week') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span class="hidden sm:inline">{{ __('schedule.copy_week') }}</span>
                    </button>

                    {{-- Paste Week --}}
                    @if($clipboardType === 'week')
                        <button
                            type="button"
                            wire:click="pasteToCurrentUser"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                            title="{{ __('schedule.paste_week') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <span class="hidden sm:inline">{{ __('schedule.paste_week') }}</span>
                        </button>
                    @endif

                    {{-- Copy From Another User --}}
                    <button
                        type="button"
                        @click="showCopyFromModal = true"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        title="{{ __('schedule.copy_from_user') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                        <span class="hidden sm:inline">{{ __('schedule.copy_from_user') }}</span>
                    </button>

                    {{-- Bulk Paste --}}
                    @if($clipboardType === 'week')
                        <button
                            type="button"
                            wire:click="openBulkPasteModal"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700"
                            title="{{ __('schedule.bulk_paste') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span class="hidden sm:inline">{{ __('schedule.bulk_paste') }}</span>
                        </button>
                    @endif

                    {{-- Reset --}}
                    <button
                        type="button"
                        @click="confirmReset = true"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-orange-700 bg-orange-100 rounded-lg hover:bg-orange-200"
                        title="{{ __('schedule.reset') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>

                    {{-- Clear All --}}
                    <button
                        type="button"
                        @click="confirmClear = true"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-red-700 bg-red-100 rounded-lg hover:bg-red-200"
                        title="{{ __('schedule.clear_all') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            @endif
        </div>

        {{-- Stats Bar --}}
        @if($selectedUserId)
            <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ __('schedule.total_hours') }}: <strong>{{ $this->totalWeeklyHours }}</strong> {{ __('schedule.hours') }}</span>
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
                        <span>{{ __('schedule.clipboard_has_' . $clipboardType) }}</span>
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
                    <p class="font-medium">{{ __('schedule.errors_found') }}</p>
                    <ul class="mt-2 list-disc list-inside text-sm space-y-1">
                        @foreach($errors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Week Grid --}}
    @if($selectedUserId)
        <div class="overflow-x-auto">
            <div class="grid grid-cols-1 md:grid-cols-7 gap-4 min-w-[800px]">
                @foreach($this->days as $dayNum => $day)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        {{-- Day Header --}}
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">
                                        {{ $day['localized'] }}
                                    </h3>
                                    <p class="text-xs text-gray-500">
                                        {{ $this->getDayHours($dayNum) }} {{ __('schedule.hours') }}
                                    </p>
                                </div>

                                {{-- Day Actions Dropdown --}}
                                <div x-data="{ open: false }" class="relative">
                                    <button
                                        @click="open = !open"
                                        class="p-1 text-gray-400 hover:text-gray-600 rounded"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                        </svg>
                                    </button>

                                    <div
                                        x-show="open"
                                        @click.away="open = false"
                                        x-transition
                                        class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10"
                                    >
                                        <button
                                            wire:click="copyDay({{ $dayNum }})"
                                            @click="open = false"
                                            class="w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"
                                        >
                                            {{ __('schedule.copy_day') }}
                                        </button>

                                        @if($clipboardType === 'day')
                                            <button
                                                wire:click="pasteDay({{ $dayNum }})"
                                                @click="open = false"
                                                class="w-full px-4 py-2 text-sm text-blue-600 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"
                                            >
                                                {{ __('schedule.paste_day') }}
                                            </button>
                                        @endif

                                        <button
                                            wire:click="applyDayToAllWeek({{ $dayNum }})"
                                            @click="open = false"
                                            class="w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"
                                        >
                                            {{ __('schedule.apply_to_all_days') }}
                                        </button>

                                        <hr class="my-1 border-gray-200">

                                        <button
                                            wire:click="clearDay({{ $dayNum }})"
                                            @click="open = false"
                                            class="w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"
                                        >
                                            {{ __('schedule.clear_day') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Shifts List --}}
                        <div class="p-3 space-y-3 min-h-[200px]">
                            @forelse($weeklySchedule[$dayNum] ?? [] as $index => $shift)
                                <div
                                    class="p-3 rounded-lg border transition-all {{
                                        ($shift['is_work_day'] ?? true)
                                            ? 'bg-blue-50 border-blue-200'
                                            : 'bg-gray-100 border-gray-300'
                                    }}"
                                    wire:key="shift-{{ $dayNum }}-{{ $index }}"
                                >
                                    {{-- Shift Header --}}
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-medium {{ ($shift['is_work_day'] ?? true) ? 'text-blue-600' : 'text-gray-500' }}">
                                            {{ __('schedule.shift') }} #{{ $index + 1 }}
                                        </span>

                                        <div class="flex items-center gap-1">
                                            {{-- Toggle Work Day --}}
                                            <button
                                                wire:click="toggleWorkDay({{ $dayNum }}, {{ $index }})"
                                                class="p-1 rounded {{ ($shift['is_work_day'] ?? true) ? 'text-green-600 hover:text-green-700' : 'text-gray-400 hover:text-gray-500' }}"
                                                title="{{ ($shift['is_work_day'] ?? true) ? __('schedule.mark_as_off') : __('schedule.mark_as_work') }}"
                                            >
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    @if($shift['is_work_day'] ?? true)
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    @else
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                    @endif
                                                </svg>
                                            </button>

                                            {{-- Remove Shift --}}
                                            <button
                                                wire:click="removeShift({{ $dayNum }}, {{ $index }})"
                                                wire:confirm="{{ __('schedule.confirm_remove_shift') }}"
                                                class="p-1 text-red-400 hover:text-red-600 rounded"
                                                title="{{ __('schedule.remove_shift') }}"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Time Inputs --}}
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">
                                                {{ __('schedule.start') }}
                                            </label>
                                            <input
                                                type="time"
                                                wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.{{ $index }}.start_time"
                                                class="w-full text-sm rounded border-gray-300 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                            >
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">
                                                {{ __('schedule.end') }}
                                            </label>
                                            <input
                                                type="time"
                                                wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.{{ $index }}.end_time"
                                                class="w-full text-sm rounded border-gray-300 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                            >
                                        </div>
                                    </div>

                                    {{-- Break Minutes --}}
                                    <div class="mt-2">
                                        <label class="block text-xs text-gray-500 mb-1">
                                            {{ __('schedule.break_minutes') }}
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="480"
                                            step="5"
                                            wire:model.live.debounce.500ms="weeklySchedule.{{ $dayNum }}.{{ $index }}.break_minutes"
                                            class="w-full text-sm rounded border-gray-300 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="0"
                                        >
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                                    <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm">{{ __('schedule.no_shifts') }}</span>
                                </div>
                            @endforelse

                            {{-- Add Shift Button --}}
                            <button
                                wire:click="addShift({{ $dayNum }})"
                                class="w-full py-2 px-3 text-sm text-gray-600 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-400 hover:text-blue-600 transition-colors"
                            >
                                <span class="flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ __('schedule.add_shift') }}
                                </span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Save Button --}}
        <div class="mt-6 flex items-center justify-end gap-3">
            <button
                wire:click="resetSchedule"
                type="button"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
                {{ __('schedule.cancel') }}
            </button>

<button
    wire:click="saveAll"
    type="button"
    class="fi-btn fi-btn-primary"
    wire:loading.attr="disabled"
    wire:target="saveAll"
>
    <span wire:loading.remove wire:target="saveAll">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </span>

    <span wire:loading wire:target="saveAll">
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
    </span>

    {{ __('schedule.save_schedule') }}
</button>
        </div>
    @else
        {{-- No Provider Selected --}}
        <div class="flex flex-col items-center justify-center py-16 text-gray-400">
            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <p class="text-lg font-medium mb-2 text-gray-600">{{ __('schedule.select_provider_prompt') }}</p>
            <p class="text-sm text-gray-500">{{ __('schedule.select_provider_desc') }}</p>
        </div>
    @endif

    {{-- Confirmation Modals --}}

    {{-- Reset Confirmation --}}
    <div
        x-show="confirmReset"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @keydown.escape.window="confirmReset = false"
    >
        <div
            class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6"
            @click.away="confirmReset = false"
        >
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                {{ __('schedule.confirm_reset_title') }}
            </h3>
            <p class="text-gray-600 mb-4">
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
                    {{ __('schedule.confirm_reset') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Clear All Confirmation --}}
    <div
        x-show="confirmClear"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @keydown.escape.window="confirmClear = false"
    >
        <div
            class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6"
            @click.away="confirmClear = false"
        >
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                {{ __('schedule.confirm_clear_title') }}
            </h3>
            <p class="text-gray-600 mb-4">
                {{ __('schedule.confirm_clear_message') }}
            </p>
            <div class="flex justify-end gap-3">
                <button
                    @click="confirmClear = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                    {{ __('schedule.cancel') }}
                </button>
                <button
                    @click="confirmClear = false; $wire.clearAllShifts()"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
                >
                    {{ __('schedule.confirm_clear') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Copy From User Modal --}}
    <div
        x-show="showCopyFromModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @keydown.escape.window="showCopyFromModal = false"
    >
        <div
            class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6"
            @click.away="showCopyFromModal = false"
        >
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ __('schedule.copy_from_user') }}
            </h3>

            <select
                x-model="copyFromUserId"
                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 mb-4"
            >
                <option value="">{{ __('schedule.choose_provider') }}</option>
                @foreach($this->providers as $provider)
                    @if($provider->id != $selectedUserId)
                        <option value="{{ $provider->id }}">
                            {{ $provider->full_name }}
                        </option>
                    @endif
                @endforeach
            </select>

            <div class="flex justify-end gap-3">
                <button
                    @click="showCopyFromModal = false; copyFromUserId = null"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                    {{ __('schedule.cancel') }}
                </button>
                <button
                    @click="if(copyFromUserId) { $wire.copyFromUser(copyFromUserId); showCopyFromModal = false; copyFromUserId = null; }"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                    :disabled="!copyFromUserId"
                    :class="{ 'opacity-50 cursor-not-allowed': !copyFromUserId }"
                >
                    {{ __('schedule.copy') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Bulk Paste Modal --}}
    @if($showBulkPasteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6 max-h-[80vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ __('schedule.bulk_paste_title') }}
                </h3>

                <p class="text-gray-600 mb-4">
                    {{ __('schedule.bulk_paste_desc') }}
                </p>

                <div class="space-y-2 max-h-60 overflow-y-auto mb-4">
                    @foreach($this->providers as $provider)
                        @if($provider->id != $selectedUserId)
                            <label class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="selectedUsersForBulkPaste"
                                    value="{{ $provider->id }}"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                >
                                <span class="text-gray-700">
                                    {{ $provider->full_name }}
                                </span>
                            </label>
                        @endif
                    @endforeach
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">
                        {{ __('schedule.selected_count', ['count' => count($selectedUsersForBulkPaste)]) }}
                    </span>

                    <div class="flex gap-3">
                        <button
                            wire:click="closeBulkPasteModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            {{ __('schedule.cancel') }}
                        </button>
                        <button
                            wire:click="applyWeekToUsers"
                            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 disabled:opacity-50"
                            @disabled(empty($selectedUsersForBulkPaste))
                        >
                            {{ __('schedule.apply_to_selected') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <style>
    [x-cloak] { display: none !important; }
</style>
</div>


