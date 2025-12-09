<x-filament-panels::page>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div class="space-y-6">
        {{-- Header / Summary --}}
        <div class="fi-section rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white shadow-xl">
            <div class="fi-section-content p-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-3">
                    <div class="p-3 rounded-xl bg-white/10 backdrop-blur">
                        <x-heroicon-o-calendar-days class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm text-slate-200">{{ __('schedule.page_heading') }}</p>
                        <h2 class="text-2xl font-semibold">
                            {{ $provider?->full_name ?? $provider?->name ?? __('resources.provider_scheduled_work.provider') }}
                        </h2>
                        <p class="text-xs text-slate-300">
                            {{ $provider?->email ?? '' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full md:w-auto">
                    <div class="bg-white/10 rounded-xl px-4 py-3">
                        <p class="text-xs text-slate-300">{{ __('resources.provider_scheduled_work.total_working_minutes') }}</p>
                        <div class="text-lg font-semibold">
                            {{ \App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm::formatMinutes($totalWorkingMinutes) }}
                        </div>
                    </div>
                    <div class="bg-white/10 rounded-xl px-4 py-3">
                        <p class="text-xs text-slate-300">{{ __('resources.provider_scheduled_work.active_days_count') }}</p>
                        <div class="text-lg font-semibold">{{ $activeDaysCount }}</div>
                    </div>
                    <div class="bg-white/10 rounded-xl px-4 py-3">
                        <p class="text-xs text-slate-300">{{ __('resources.provider_scheduled_work.total_shifts_count') }}</p>
                        <div class="text-lg font-semibold">{{ $totalShiftsCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-700">
            <span class="inline-flex items-center gap-2">
                <span class="h-3 w-3 rounded bg-emerald-500"></span>
                {{ __('schedule.shift_block') ?? 'Active shift' }}
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-3 w-3 rounded bg-amber-400/80"></span>
                {{ __('schedule.day_off_indicator') ?? 'Inactive' }}
            </span>
        </div>

        {{-- Timeline --}}
        <div class="space-y-5">
            @foreach($weeklySchedule as $dayNumber => $day)
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-950/5 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-clock class="w-5 h-5 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">
                                {{ $day['label'] }}
                            </h3>
                        </div>
                        <div class="flex items-center gap-3">
                            @if(isset($day['total_minutes']) && $day['total_minutes'] > 0)
                                <div class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md">
                                    {{ \App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm::formatMinutes($day['total_minutes']) }}
                                </div>
                            @endif
                            <div class="text-xs text-gray-500">
                                {{ count($day['shifts']) }} {{ __('resources.provider_scheduled_work.shifts') }}
                            </div>
                        </div>
                    </div>

                    {{-- Time ruler --}}
                    <div class="mb-2 grid gap-2 text-[11px] text-gray-500"
                         style="grid-template-columns: repeat({{ count($timeSlots) }}, minmax(0,1fr));">
                        @foreach($timeSlots as $index => $slot)
                            @if($index % 2 === 0)
                                <div class="text-center">{{ $slot }}</div>
                            @else
                                <div></div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Timeline bar --}}
                    <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                        <div class="grid gap-px"
                             style="grid-template-columns: repeat({{ count($timeSlots) }}, minmax(0,1fr));">
                            @foreach($timeSlots as $i => $slot)
                                <div class="h-8 @if($i % 2 === 0) bg-gray-50/60 @else bg-gray-100/60 @endif"></div>
                            @endforeach
                        </div>

                        {{-- Shifts --}}
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full grid"
                                 style="grid-template-columns: repeat({{ count($timeSlots) }}, minmax(0,1fr));">
                                @foreach($day['shifts'] as $shift)
                                    @php
                                        $startSlot = $shift['start_slot'];
                                        $endSlot = $shift['end_slot'];
                                        $span = max(1, $endSlot - $startSlot);
                                        $color = $shift['is_active'] ? 'bg-emerald-500/80 border-emerald-600' : 'bg-amber-400/70 border-amber-500';
                                    @endphp
                                    <div class="relative h-8" style="grid-column: {{ $startSlot + 1 }} / span {{ $span }};">
                                        <div class="absolute inset-0 rounded-lg border text-[11px] font-medium text-white flex items-center justify-center {{ $color }}">
                                            {{ $shift['start'] }} - {{ $shift['end'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
