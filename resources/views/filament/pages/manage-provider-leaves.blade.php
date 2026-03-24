<x-filament-panels::page>
    <style>
        .mpl-stats-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        @media (min-width: 768px) {
            .mpl-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1280px) {
            .mpl-stats-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .mpl-stat-card {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1.25rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }
        .dark .mpl-stat-card {
            border-color: rgba(255, 255, 255, 0.08);
            background: linear-gradient(180deg, #111827 0%, #0f172a 100%);
            box-shadow: none;
        }
        .mpl-stat-card::after {
            content: "";
            position: absolute;
            inset-inline-end: -1.5rem;
            inset-block-start: -1.5rem;
            width: 5rem;
            height: 5rem;
            border-radius: 9999px;
            opacity: 0.14;
        }
        .mpl-stat-card[data-tone="blue"]::after { background: #2563eb; }
        .mpl-stat-card[data-tone="cyan"]::after { background: #0891b2; }
        .mpl-stat-card[data-tone="green"]::after { background: #059669; }
        .mpl-stat-card[data-tone="amber"]::after { background: #d97706; }
        .mpl-stat-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.9rem;
        }
        .mpl-stat-label {
            margin: 0;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .dark .mpl-stat-label {
            color: #9ca3af;
        }
        .mpl-stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.85rem;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.18);
        }
        .dark .mpl-stat-icon {
            background: rgba(17, 24, 39, 0.9);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
        }
        .mpl-stat-value {
            margin: 0;
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
            color: #111827;
        }
        .dark .mpl-stat-value {
            color: #f9fafb;
        }
        .mpl-stat-meta {
            margin-top: 0.6rem;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .dark .mpl-stat-meta {
            color: #9ca3af;
        }
        .mpl-page-stack {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
    </style>

    <div class="mpl-page-stack">
        {{-- Statistics Cards --}}
        <div class="mpl-stats-grid">
            @php
                $totalLeaves = \App\Models\ProviderTimeOff::count();
                $upcomingLeaves = \App\Models\ProviderTimeOff::where('start_date', '>=', now()->toDateString())->count();
                $activeLeaves = \App\Models\ProviderTimeOff::where('start_date', '<=', now()->toDateString())
                    ->where('end_date', '>=', now()->toDateString())->count();
                $thisMonthLeaves = \App\Models\ProviderTimeOff::whereYear('start_date', now()->year)
                    ->whereMonth('start_date', now()->month)->count();
            @endphp

            <div class="mpl-stat-card" data-tone="blue">
                <div class="mpl-stat-head">
                    <p class="mpl-stat-label">{{ __('resources.provider_resource.total_leaves') }}</p>
                    <span class="mpl-stat-icon">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5 text-blue-600" />
                    </span>
                </div>
                <p class="mpl-stat-value">{{ $totalLeaves }}</p>
                <div class="mpl-stat-meta">{{ __('resources.provider_resource.all_provider_leaves') }}</div>
            </div>

            <div class="mpl-stat-card" data-tone="cyan">
                <div class="mpl-stat-head">
                    <p class="mpl-stat-label">{{ __('resources.provider_resource.upcoming_leaves') }}</p>
                    <span class="mpl-stat-icon">
                        <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-cyan-600" />
                    </span>
                </div>
                <p class="mpl-stat-value">{{ $upcomingLeaves }}</p>
                <div class="mpl-stat-meta">{{ __('resources.provider_resource.upcoming') }}</div>
            </div>

            <div class="mpl-stat-card" data-tone="green">
                <div class="mpl-stat-head">
                    <p class="mpl-stat-label">{{ __('resources.provider_resource.active_leaves') }}</p>
                    <span class="mpl-stat-icon">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-emerald-600" />
                    </span>
                </div>
                <p class="mpl-stat-value">{{ $activeLeaves }}</p>
                <div class="mpl-stat-meta">{{ __('resources.provider_resource.active') }}</div>
            </div>

            <div class="mpl-stat-card" data-tone="amber">
                <div class="mpl-stat-head">
                    <p class="mpl-stat-label">{{ __('resources.provider_resource.this_month') }}</p>
                    <span class="mpl-stat-icon">
                        <x-filament::icon icon="heroicon-o-calendar" class="h-5 w-5 text-amber-600" />
                    </span>
                </div>
                <p class="mpl-stat-value">{{ $thisMonthLeaves }}</p>
                <div class="mpl-stat-meta">{{ now()->format('F Y') }}</div>
            </div>
        </div>

        {{-- Table --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
