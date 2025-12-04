@php
    $duration = $duration ?? 0;
    $suffix = $suffix ?? 'دقيقة';
@endphp

<div class="flex items-center gap-2 rounded-lg bg-primary-50 dark:bg-primary-900/20 px-4 py-2.5 border border-primary-200 dark:border-primary-800">
    <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <div class="flex items-baseline gap-1.5">
        <span class="text-2xl font-bold text-primary-700 dark:text-primary-300">{{ $duration }}</span>
        <span class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ $suffix }}</span>
    </div>
</div>
