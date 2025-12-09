<div class="weekly-schedule-timeline">
    {{-- Header with Statistics --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Work Days Card --}}
        <div class="rounded-lg bg-primary-50 p-4 ">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 ">
                    <x-heroicon-o-calendar-days class="h-5 w-5 text-primary-600 " />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 ">
                        {{ __('resources.provider_scheduled_work.work_days_count') }}
                    </p>
                    <p class="text-2xl font-bold text-primary-600 ">
                        {{ $totalWorkDays }}<span class="text-sm font-normal">/7</span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Weekly Hours Card --}}
        <div class="rounded-lg bg-success-50 p-4 ">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-success-100 ">
                    <x-heroicon-o-clock class="h-5 w-5 text-success-600 " />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 ">
                        {{ __('resources.provider_scheduled_work.weekly_hours') }}
                    </p>
                    <p class="text-2xl font-bold text-success-600 ">
                        {{ $totalWeeklyHours }}<span class="text-sm font-normal">h</span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Total Shifts Card --}}
        <div class="rounded-lg bg-info-50 p-4 ">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-info-100 ">
                    <x-heroicon-o-squares-2x2 class="h-5 w-5 text-info-600 " />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 ">
                        {{ __('schedule.total_shifts') }}
                    </p>
                    <p class="text-2xl font-bold text-info-600 ">
                        {{ $totalShifts }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Timeline for Each Day --}}
    <div class="space-y-4">
        @foreach($weeklySchedule as $day => $dayData)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition  ">
                {{-- Day Header --}}
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        {{-- Day Icon --}}
                        <div class="flex h-10 w-10 items-center justify-center rounded-full
                            @if($dayData['is_work_day'])
                                bg-{{ $this->getDayColor($day) }}-100 dark:bg-{{ $this->getDayColor($day) }}-900/30
                            @else
                                bg-gray-100
                            @endif
                        ">
                            @php
                                $icon = $this->getDayIcon($day);
                                $iconClass = $dayData['is_work_day']
                                    ? "h-5 w-5 text-{$this->getDayColor($day)}-600 dark:text-{$this->getDayColor($day)}-400"
                                    : "h-5 w-5 text-gray-400 ";
                            @endphp
                            <x-dynamic-component :component="$icon" :class="$iconClass" />
                        </div>

                        {{-- Day Name --}}
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 ">
                                {{ $this->getLocalizedDayName($day) }}
                            </h3>
                            @if($dayData['is_work_day'])
                                <p class="text-xs text-gray-500 ">
                                    {{ $dayData['effective_hours'] }}h {{ __('resources.provider_scheduled_work.effective_hours') }}
                                    @if(count($dayData['shifts']) > 0)
                                        • {{ count($dayData['shifts']) }} {{ __('schedule.shifts') }}
                                    @endif
                                </p>
                            @else
                                <p class="text-xs font-medium text-gray-500 ">
                                    {{ __('resources.provider_scheduled_work.day_off') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Branch Schedule Info --}}
                    @if($showBranchSchedule && $this->isBranchOpen($day))
                        @php
                            $branchSched = $this->getBranchScheduleForDay($day);
                        @endphp
                        <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs ">
                            <x-heroicon-o-building-storefront class="h-4 w-4 text-gray-400" />
                            <span class="font-medium text-gray-600 ">
                                {{ __('schedule.branch') }}:
                            </span>
                            <span class="font-mono font-semibold text-gray-900 ">
                                {{ $branchSched['open_time'] }} - {{ $branchSched['close_time'] }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Timeline Bar --}}
                <div class="relative">
                    {{-- Timeline Background --}}
                    <div class="relative h-16 overflow-hidden rounded-lg
                        @if($dayData['is_work_day'])
                            bg-gradient-to-r from-gray-50 via-gray-100 to-gray-50
                        @else
                            bg-gradient-to-r from-gray-100 via-gray-200 to-gray-100
                            opacity-50
                        @endif
                    ">
                        {{-- Hour Markers --}}
                        <div class="absolute inset-0 flex">
                            @for($hour = 0; $hour < 24; $hour++)
                                <div class="flex-1 border-r border-gray-300/30"
                                     style="width: {{ 100/24 }}%">
                                    @if($hour % 3 == 0)
                                        <span class="absolute -top-5 text-xs font-medium text-gray-500 "
                                              style="left: {{ ($hour / 24) * 100 }}%">
                                            {{ sprintf('%02d:00', $hour) }}
                                        </span>
                                    @endif
                                </div>
                            @endfor
                        </div>

                        {{-- Branch Schedule Background (if exists) --}}
                        @if($this->isBranchOpen($day))
                            @php
                                $branchSched = $this->getBranchScheduleForDay($day);
                                $branchStart = \App\Models\ProviderScheduledWork::timeToMinutes($branchSched['open_time']);
                                $branchEnd = \App\Models\ProviderScheduledWork::timeToMinutes($branchSched['close_time']);
                                $branchDuration = $branchEnd - $branchStart;
                                $branchStartPercent = ($branchStart / (24 * 60)) * 100;
                                $branchWidthPercent = ($branchDuration / (24 * 60)) * 100;
                            @endphp
                            <div class="absolute inset-y-0 bg-blue-100/30 "
                                 style="left: {{ $branchStartPercent }}%; width: {{ $branchWidthPercent }}%"
                                 title="{{ __('schedule.branch_hours') }}">
                            </div>
                        @endif

                        {{-- Shifts --}}
                        @if($dayData['is_work_day'] && count($dayData['shifts']) > 0)
                            @foreach($dayData['shifts'] as $index => $shift)
                                @php
                                    $colors = [
                                        'bg-gradient-to-r from-primary-500 to-primary-600',
                                        'bg-gradient-to-r from-success-500 to-success-600',
                                        'bg-gradient-to-r from-warning-500 to-warning-600',
                                        'bg-gradient-to-r from-info-500 to-info-600',
                                    ];
                                    $colorClass = $colors[$index % count($colors)];
                                @endphp
                                <div class="group absolute inset-y-2 rounded-md shadow-md transition-all hover:inset-y-0 hover:z-10 hover:shadow-xl {{ $colorClass }}"
                                     style="left: {{ $shift['start_percentage'] }}%; width: {{ $shift['duration_percentage'] }}%">

                                    {{-- Shift Content --}}
                                    <div class="flex h-full flex-col justify-center px-2 text-white">
                                        <div class="text-xs font-bold">
                                            {{ $shift['start_time'] }} - {{ $shift['end_time'] }}
                                        </div>
                                        @if($shift['break_minutes'] > 0)
                                            <div class="text-[10px] opacity-90">
                                                <x-heroicon-o-pause class="inline h-3 w-3" />
                                                {{ $shift['break_minutes'] }}{{ __('resources.provider_scheduled_work.minutes') }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Hover Tooltip --}}
                                    <div class="pointer-events-none absolute -top-20 left-1/2 z-20 hidden -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-xl group-hover:block ">
                                        <div class="space-y-1">
                                            <div class="font-bold">{{ __('schedule.shift') }} #{{ $index + 1 }}</div>
                                            <div>⏰ {{ $shift['start_time'] }} - {{ $shift['end_time'] }}</div>
                                            <div>⏱️ {{ floor($shift['total_minutes'] / 60) }}h {{ $shift['total_minutes'] % 60 }}m</div>
                                            @if($shift['break_minutes'] > 0)
                                                <div>☕ {{ __('resources.provider_scheduled_work.break_duration') }}: {{ $shift['break_minutes'] }}m</div>
                                            @endif
                                            <div>✅ {{ __('resources.provider_scheduled_work.effective_hours') }}: {{ floor($shift['effective_minutes'] / 60) }}h {{ $shift['effective_minutes'] % 60 }}m</div>
                                        </div>
                                        {{-- Tooltip Arrow --}}
                                        <div class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 rotate-45 bg-gray-900 "></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        {{-- No Shifts Message --}}
                        @if(!$dayData['is_work_day'] || count($dayData['shifts']) == 0)
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="rounded-lg bg-white/80 px-4 py-2 text-center shadow-sm">
                                    <x-heroicon-o-calendar-days class="mx-auto h-6 w-6 text-gray-400" />
                                    <p class="mt-1 text-xs font-medium text-gray-500 ">
                                        @if(!$dayData['is_work_day'])
                                            {{ __('resources.provider_scheduled_work.day_off') }}
                                        @else
                                            {{ __('schedule.no_shifts') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Time Labels (Bottom) --}}
                    <div class="mt-1 flex justify-between text-[10px] font-medium text-gray-400">
                        <span>00:00</span>
                        <span>06:00</span>
                        <span>12:00</span>
                        <span>18:00</span>
                        <span>24:00</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Legend --}}
    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 ">
        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700 ">
            <x-heroicon-o-information-circle class="h-5 w-5" />
            {{ __('schedule.legend') }}
        </h4>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="flex items-center gap-2">
                <div class="h-4 w-8 rounded bg-gradient-to-r from-primary-500 to-primary-600"></div>
                <span class="text-xs text-gray-600 ">{{ __('schedule.shift') }} 1</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-8 rounded bg-gradient-to-r from-success-500 to-success-600"></div>
                <span class="text-xs text-gray-600 ">{{ __('schedule.shift') }} 2</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-8 rounded bg-blue-100/30 "></div>
                <span class="text-xs text-gray-600 ">{{ __('schedule.branch_hours') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-8 rounded bg-gray-200 "></div>
                <span class="text-xs text-gray-600 ">{{ __('resources.provider_scheduled_work.day_off') }}</span>
            </div>
        </div>
    </div>

    <style>
    /* إضافة animations سلسة */
    .weekly-schedule-timeline {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* تحسين مظهر الـ scrollbar */
    .weekly-schedule-timeline::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .weekly-schedule-timeline::-webkit-scrollbar-track {
        background: transparent;
    }

    .weekly-schedule-timeline::-webkit-scrollbar-thumb {
        background: rgba(156, 163, 175, 0.5);
        border-radius: 4px;
    }

    .weekly-schedule-timeline::-webkit-scrollbar-thumb:hover {
        background: rgba(156, 163, 175, 0.7);
    }

    /* Dark mode support */
    .dark .weekly-schedule-timeline::-webkit-scrollbar-thumb {
        background: rgba(75, 85, 99, 0.5);
    }

    .dark .weekly-schedule-timeline::-webkit-scrollbar-thumb:hover {
        background: rgba(75, 85, 99, 0.7);
    }
</style>
</div>


