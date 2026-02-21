<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Prints</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($stats['total_prints']) }}
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
            <div class="text-sm text-green-600 dark:text-green-400">Successful</div>
            <div class="text-2xl font-bold text-green-700 dark:text-green-300">
                {{ number_format($stats['successful_prints']) }}
            </div>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
            <div class="text-sm text-red-600 dark:text-red-400">Failed</div>
            <div class="text-2xl font-bold text-red-700 dark:text-red-300">
                {{ number_format($stats['failed_prints']) }}
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="text-sm text-blue-600 dark:text-blue-400">Today</div>
            <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">
                {{ number_format($stats['prints_today']) }}
            </div>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Average Duration</div>
        <div class="text-xl font-bold text-gray-900 dark:text-white">
            {{ $stats['average_duration_ms'] ? round($stats['average_duration_ms'] / 1000, 2) . 's' : 'N/A' }}
        </div>
    </div>

    @if($stats['total_prints'] > 0)
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Success Rate</div>
        <div class="flex items-center gap-2">
            <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                    class="bg-green-500 h-2 rounded-full"
                    style="width: {{ ($stats['successful_prints'] / $stats['total_prints']) * 100 }}%"
                ></div>
            </div>
            <div class="text-lg font-bold text-gray-900 dark:text-white">
                {{ round(($stats['successful_prints'] / $stats['total_prints']) * 100, 1) }}%
            </div>
        </div>
    </div>
    @endif
</div>
