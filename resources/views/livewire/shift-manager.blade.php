<div class="shift-manager">
    {{-- Header with Save Button --}}
    <div class="mb-6 flex items-center justify-between rounded-xl bg-gradient-to-r from-primary-50 to-primary-100 p-6 dark:from-primary-950/30 dark:to-primary-900/30">
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-500 shadow-lg">
                <x-heroicon-o-calendar-days class="h-6 w-6 text-white" />
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ __('schedule.page_title') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('schedule.page_subheading') }}
                </p>
            </div>
        </div>

        @if(!$readOnly)
            <button
                wire:click="saveShifts"
                class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-success-600 to-success-700 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:from-success-700 hover:to-success-800 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-success-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                <x-heroicon-m-check-circle class="h-5 w-5" />
                {{ __('schedule.save_schedule') }}
            </button>
        @endif
    </div>

    {{-- Success Message --}}
    @if (session()->has('success'))
        <div class="mb-6 animate-slideIn rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-900/50 dark:bg-success-950/20">
            <div class="flex items-center gap-3">
                <x-heroicon-m-check-badge class="h-6 w-6 text-success-600 dark:text-success-400" />
                <p class="font-medium text-success-900 dark:text-success-100">
                    {{ session('success') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Days Grid --}}
    <div class="space-y-4">
        @foreach($daysOfWeek as $dayNum => $dayName)
            <div class="group rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                {{-- Day Header --}}
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg
                            {{ $shifts[$dayNum]['is_work_day'] ? 'bg-primary-100 dark:bg-primary-900/30' : 'bg-gray-100 dark:bg-gray-800' }}">
                            <span class="text-lg font-bold {{ $shifts[$dayNum]['is_work_day'] ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400' }}">
                                {{ substr(__('schedule.' . $dayName), 0, 2) }}
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ __('schedule.' . $dayName) }}
                        </h3>
                        @if($shifts[$dayNum]['is_work_day'])
                            <span class="rounded-full bg-success-100 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-900/30 dark:text-success-300">
                                {{ count($shifts[$dayNum]['items']) }} {{ __('schedule.shifts') }}
                            </span>
                        @else
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {{ __('resources.provider_scheduled_work.day_off') }}
                            </span>
                        @endif
                    </div>

                    @if(!$readOnly)
                        <div class="flex items-center gap-2">
                            <button
                                wire:click="toggleDayOff({{ $dayNum }})"
                                class="rounded-lg px-3 py-2 text-sm font-medium transition
                                    {{ $shifts[$dayNum]['is_work_day'] ? 'bg-warning-100 text-warning-700 hover:bg-warning-200 dark:bg-warning-900/30 dark:text-warning-300 dark:hover:bg-warning-900/50' : 'bg-success-100 text-success-700 hover:bg-success-200 dark:bg-success-900/30 dark:text-success-300 dark:hover:bg-success-900/50' }}">
                                {{ $shifts[$dayNum]['is_work_day'] ? __('schedule.mark_as_off') : __('schedule.mark_as_work') }}
                            </button>

                            @if($shifts[$dayNum]['is_work_day'])
                                <button
                                    wire:click="addShift({{ $dayNum }})"
                                    class="rounded-lg bg-primary-100 px-3 py-2 text-sm font-medium text-primary-700 transition hover:bg-primary-200 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50">
                                    <x-heroicon-m-plus class="inline h-4 w-4" />
                                    {{ __('schedule.add_shift') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Shifts List --}}
                @if($shifts[$dayNum]['is_work_day'] && count($shifts[$dayNum]['items']) > 0)
                    <div class="space-y-3">
                        @foreach($shifts[$dayNum]['items'] as $index => $shift)
                            <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-500 text-sm font-bold text-white">
                                    {{ $index + 1 }}
                                </div>

                                <div class="flex flex-1 items-center gap-4">
                                    {{-- Start Time --}}
                                    <div class="flex-1">
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ __('schedule.start') }}
                                        </label>
                                        <input
                                            type="time"
                                            wire:model="shifts.{{ $dayNum }}.items.{{ $index }}.start_time"
                                            {{ $readOnly ? 'disabled' : '' }}
                                            class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                    </div>

                                    {{-- End Time --}}
                                    <div class="flex-1">
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ __('schedule.end') }}
                                        </label>
                                        <input
                                            type="time"
                                            wire:model="shifts.{{ $dayNum }}.items.{{ $index }}.end_time"
                                            {{ $readOnly ? 'disabled' : '' }}
                                            class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                    </div>

                                    {{-- Break Minutes --}}
                                    <div class="w-32">
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ __('schedule.break_minutes') }}
                                        </label>
                                        <input
                                            type="number"
                                            wire:model="shifts.{{ $dayNum }}.items.{{ $index }}.break_minutes"
                                            min="0"
                                            {{ $readOnly ? 'disabled' : '' }}
                                            class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                    </div>
                                </div>

                                @if(!$readOnly)
                                    <button
                                        wire:click="removeShift({{ $dayNum }}, {{ $index }})"
                                        class="rounded-lg bg-danger-100 p-2 text-danger-600 transition hover:bg-danger-200 dark:bg-danger-900/30 dark:text-danger-400 dark:hover:bg-danger-900/50">
                                        <x-heroicon-m-trash class="h-5 w-5" />
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <style>
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .animate-slideIn {
        animation: slideIn 0.3s ease-out;
    }

    .shift-manager {
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
</style>

</div>

