@php
    /**
     * One attendance "line": faint scheduled band(s) overlaid by solid actual bar(s).
     * All positions are pre-computed percentages from AttendanceBoardService.
     * Light theme only (the panel's `dark:` is OS-driven in Tailwind v4, so it's avoided).
     *
     * @var array  $day      timeline payload
     * @var string $variant  'hero' | 'mini' | 'modal'
     */
    $variant = $variant ?? 'hero';
    $trackHeight = match ($variant) {
        'mini'  => 'h-2',
        default => 'h-3.5',
    };
    $showAxis = $variant !== 'mini';
@endphp

<div class="w-full">
    {{-- Label row: date + total --}}
    <div class="flex items-center justify-between gap-2 {{ $variant === 'mini' ? 'mb-1' : 'mb-2' }}">
        <div class="flex min-w-0 items-center gap-1.5">
            <span class="truncate {{ $variant === 'mini' ? 'text-xs text-gray-500' : 'text-sm font-semibold text-gray-800' }}">
                {{ $day['date_label'] }}
            </span>

            @if ($day['has_open'])
                <span class="relative flex h-1.5 w-1.5" title="{{ __('attendance_board.no_checkout') }}">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                </span>
            @endif
        </div>

        <div class="shrink-0">
            @if ($day['has_records'])
                <span class="{{ $variant === 'mini' ? 'text-xs font-medium text-gray-600' : 'text-sm font-bold text-gray-900' }}">
                    {{ $day['total_human'] }}
                </span>
            @else
                <span class="text-xs text-gray-400">{{ __('attendance_board.no_records_day') }}</span>
            @endif
        </div>
    </div>

    {{-- The track --}}
    <div class="relative w-full {{ $trackHeight }} overflow-hidden rounded-full bg-gray-100 ring-1 ring-inset ring-gray-200/70">
        {{-- Scheduled band(s) — faint indigo reference --}}
        @foreach ($day['bands'] as $band)
            <div
                class="absolute inset-y-0 bg-indigo-100 ring-1 ring-inset ring-indigo-300/70"
                style="inset-inline-start: {{ $band['left'] }}%; width: {{ $band['width'] }}%"
                title="{{ __('attendance_board.scheduled') }}: {{ $band['range'] }}"
            ></div>
        @endforeach

        {{-- Actual attendance bar(s) — solid foreground --}}
        @foreach ($day['bars'] as $bar)
            <div
                class="absolute inset-y-0 rounded-full shadow-sm {{ $bar['is_open'] ? 'attendance-stripes bg-amber-500' : 'bg-emerald-500' }}"
                style="inset-inline-start: {{ $bar['left'] }}%; width: {{ $bar['width'] }}%"
                title="{{ __('attendance_board.actual') }}: {{ $bar['in'] }}–{{ $bar['out'] ?? __('attendance_board.no_checkout') }}{{ $bar['duration'] ? ' · ' . $bar['duration'] : '' }}"
            ></div>
        @endforeach
    </div>

    {{-- Axis end labels --}}
    @if ($showAxis)
        <div class="relative mt-1 h-3 text-[10px] tabular-nums text-gray-400">
            <span class="absolute" style="inset-inline-start: 0">{{ $day['axis_start'] }}</span>
            <span class="absolute" style="inset-inline-end: 0">{{ $day['axis_end'] }}</span>
        </div>
    @endif
</div>
