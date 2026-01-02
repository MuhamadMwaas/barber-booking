<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Statistics Cards --}}
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @php
                $totalLeaves = \App\Models\ProviderTimeOff::count();
                $upcomingLeaves = \App\Models\ProviderTimeOff::where('start_date', '>=', now()->toDateString())->count();
                $activeLeaves = \App\Models\ProviderTimeOff::where('start_date', '<=', now()->toDateString())
                    ->where('end_date', '>=', now()->toDateString())->count();
                $thisMonthLeaves = \App\Models\ProviderTimeOff::whereYear('start_date', now()->year)
                    ->whereMonth('start_date', now()->month)->count();
            @endphp

            <x-filament::stats.card
                :label="__('resources.provider_resource.total_leaves')"
                :value="$totalLeaves"
                icon="heroicon-o-calendar-days"
                color="primary"
            />

            <x-filament::stats.card
                :label="__('resources.provider_resource.upcoming_leaves')"
                :value="$upcomingLeaves"
                icon="heroicon-o-clock"
                color="info"
            />

            <x-filament::stats.card
                :label="__('resources.provider_resource.active_leaves')"
                :value="$activeLeaves"
                icon="heroicon-o-check-circle"
                :color="$activeLeaves > 0 ? 'success' : 'gray'"
            />

            <x-filament::stats.card
                :label="__('resources.provider_resource.this_month')"
                :value="$thisMonthLeaves"
                icon="heroicon-o-calendar"
                color="warning"
            />
        </div>

        {{-- Table --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
