<x-filament-panels::page>
    <style>
        .pr-grid-6 { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; }
        .pr-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
        .pr-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .pr-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .pr-card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.05); padding: 1rem; }
        .dark .pr-card { background: rgb(24, 24, 27); border-color: rgba(255,255,255,0.1); }
        .pr-stat-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.25rem; }
        .dark .pr-stat-label { color: #9ca3af; }
        .pr-stat-value { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .dark .pr-stat-value { color: white; }
        .pr-stat-value-sm { font-size: 1.25rem; }
        .pr-stat-green { color: #059669; }
        .pr-stat-red { color: #dc2626; }
        .pr-stat-amber { color: #d97706; }
        .pr-stat-yellow { color: #ca8a04; }
        .pr-stat-blue { color: #2563eb; }
        .pr-section-title { font-size: 0.95rem; font-weight: 600; color: #111827; margin-bottom: 1rem; }
        .dark .pr-section-title { color: white; }
        .pr-chart-container { height: 280px; position: relative; }
        .pr-table { width: 100%; font-size: 0.875rem; }
        .pr-table th { text-align: start; padding: 0.5rem 0.75rem; font-weight: 500; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        .dark .pr-table th { color: #9ca3af; border-color: #374151; }
        .pr-table td { padding: 0.5rem 0.75rem; color: #374151; border-bottom: 1px solid #f3f4f6; }
        .dark .pr-table td { color: #d1d5db; border-color: #1f2937; }
        .pr-table td.font-medium { font-weight: 500; color: #111827; }
        .dark .pr-table td.font-medium { color: white; }
        .pr-preset-btn { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; transition: all 0.15s; cursor: pointer; border: none; }
        .pr-preset-btn.active { background: #f59e0b; color: white; }
        .pr-preset-btn.inactive { background: #f3f4f6; color: #374151; }
        .pr-preset-btn.inactive:hover { background: #e5e7eb; }
        .dark .pr-preset-btn.inactive { background: #374151; color: #d1d5db; }
        .pr-date-input { border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.375rem 0.75rem; font-size: 0.875rem; }
        .dark .pr-date-input { background: #374151; border-color: #4b5563; color: #d1d5db; }
        .pr-select { border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; font-size: 0.875rem; min-width: 200px; background: white; }
        .dark .pr-select { background: #374151; border-color: #4b5563; color: #d1d5db; }
        .pr-progress-bg { width: 100%; background: #e5e7eb; border-radius: 9999px; height: 0.625rem; }
        .dark .pr-progress-bg { background: #374151; }
        .pr-progress-bar { height: 0.625rem; border-radius: 9999px; transition: width 0.3s; }
        .pr-loading { position: fixed; inset: 0; background: rgba(0,0,0,0.2); z-index: 50; display: flex; align-items: center; justify-content: center; }
        .pr-loading-box { background: white; border-radius: 0.75rem; padding: 1rem 1.5rem; box-shadow: 0 10px 15px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.75rem; }
        .dark .pr-loading-box { background: #1f2937; }
        .pr-badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .pr-badge-green { background: #d1fae5; color: #065f46; }
        .dark .pr-badge-green { background: #064e3b; color: #6ee7b7; }
        .pr-badge-red { background: #fee2e2; color: #991b1b; }
        .dark .pr-badge-red { background: #7f1d1d; color: #fca5a5; }
        .pr-badge-amber { background: #fef3c7; color: #92400e; }
        .dark .pr-badge-amber { background: #78350f; color: #fcd34d; }
        .pr-badge-gray { background: #f3f4f6; color: #374151; }
        .dark .pr-badge-gray { background: #374151; color: #d1d5db; }
        .pr-provider-header { display: flex; align-items: center; gap: 1rem; padding: 0.5rem 0; }
        .pr-provider-avatar { width: 3rem; height: 3rem; border-radius: 9999px; background: #f59e0b; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem; }
        .pr-provider-name { font-size: 1.1rem; font-weight: 600; color: #111827; }
        .dark .pr-provider-name { color: white; }
        .pr-provider-meta { font-size: 0.8rem; color: #6b7280; }
        .dark .pr-provider-meta { color: #9ca3af; }
        .pr-print-btn { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; background: #3b82f6; color: white; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; }
        .pr-print-btn:hover { background: #2563eb; }
        @media print {
            .pr-no-print { display: none !important; }
            .pr-card { break-inside: avoid; box-shadow: none; border: 1px solid #e5e7eb; }
            body { font-size: 12px; }
        }
        @media (max-width: 1024px) {
            .pr-grid-6 { grid-template-columns: repeat(3, 1fr); }
            .pr-grid-4 { grid-template-columns: repeat(2, 1fr); }
            .pr-grid-3 { grid-template-columns: repeat(1, 1fr); }
            .pr-grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .pr-grid-6 { grid-template-columns: repeat(2, 1fr); }
            .pr-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
    </style>

    <div x-data="providerReportPage()">

        {{-- Controls: Provider Select + Date Range + Print --}}
        <div class="pr-card pr-no-print" style="margin-bottom: 1.5rem;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #374151;">{{ __('provider_report.select_provider') }}</label>
                    <select wire:model.live="selectedProviderId" class="pr-select">
                        @foreach($providers as $p)
                            <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <button wire:click="setPreset('week')" class="pr-preset-btn {{ $activePreset === 'week' ? 'active' : 'inactive' }}">
                        {{ __('provider_report.presets.week') }}
                    </button>
                    <button wire:click="setPreset('month')" class="pr-preset-btn {{ $activePreset === 'month' ? 'active' : 'inactive' }}">
                        {{ __('provider_report.presets.month') }}
                    </button>
                    <button wire:click="setPreset('year')" class="pr-preset-btn {{ $activePreset === 'year' ? 'active' : 'inactive' }}">
                        {{ __('provider_report.presets.year') }}
                    </button>
                    <button wire:click="setPreset('all')" class="pr-preset-btn {{ $activePreset === 'all' ? 'active' : 'inactive' }}">
                        {{ __('provider_report.presets.all') }}
                    </button>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem; margin-inline-start: auto;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <label style="font-size: 0.875rem; color: #6b7280;">{{ __('provider_report.from') }}</label>
                        <input type="date" wire:model.live.debounce.500ms="dateFrom" class="pr-date-input">
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <label style="font-size: 0.875rem; color: #6b7280;">{{ __('provider_report.to') }}</label>
                        <input type="date" wire:model.live.debounce.500ms="dateTo" class="pr-date-input">
                    </div>
                    <button onclick="window.print()" class="pr-print-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        {{ __('provider_report.print') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Loading --}}
        <div wire:loading class="pr-loading pr-no-print">
            <div class="pr-loading-box">
                <svg style="animation: spin 1s linear infinite; height: 1.25rem; width: 1.25rem; color: #f59e0b;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">{{ __('provider_report.loading') }}</span>
            </div>
        </div>

        @if($selectedProviderId)
            {{-- Provider Info Header --}}
            @if(!empty($providerInfo))
                <div class="pr-card" style="margin-bottom: 1.5rem;">
                    <div class="pr-provider-header">
                        <div class="pr-provider-avatar">
                            {{ strtoupper(substr($providerInfo['name'] ?? '', 0, 1)) }}
                        </div>
                        <div>
                            <div class="pr-provider-name">{{ $providerInfo['name'] ?? '' }}</div>
                            <div class="pr-provider-meta">
                                {{ $providerInfo['email'] ?? '' }}
                                @if($providerInfo['phone'] ?? false) &middot; {{ $providerInfo['phone'] }} @endif
                                @if($providerInfo['active_services'] ?? 0) &middot; {{ $providerInfo['active_services'] }} {{ __('provider_report.active_services') }} @endif
                            </div>
                        </div>
                        <div style="margin-inline-start: auto; text-align: end;">
                            @if(!empty($utilization))
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div>
                                        <div class="pr-stat-label">{{ __('provider_report.stats.utilization') }}</div>
                                        <div class="pr-stat-value pr-stat-value-sm pr-stat-blue">{{ $utilization['utilization'] ?? 0 }}%</div>
                                    </div>
                                    <div style="width: 100px;">
                                        <div class="pr-progress-bg">
                                            <div class="pr-progress-bar" style="width: {{ min($utilization['utilization'] ?? 0, 100) }}%; background: {{ ($utilization['utilization'] ?? 0) >= 70 ? '#059669' : (($utilization['utilization'] ?? 0) >= 40 ? '#f59e0b' : '#ef4444') }};"></div>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.65rem; color: #9ca3af; margin-top: 2px;">
                                            <span>{{ $utilization['worked_hours'] ?? 0 }}h</span>
                                            <span>/ {{ $utilization['available_hours'] ?? 0 }}h</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- KPI Stats Row 1 --}}
            <div class="pr-grid-6" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.total_revenue') }}</p>
                    <p class="pr-stat-value">&euro;{{ number_format($revenueStats['total'] ?? 0, 2) }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.avg_booking') }}</p>
                    <p class="pr-stat-value">&euro;{{ number_format($revenueStats['average'] ?? 0, 2) }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.total_bookings') }}</p>
                    <p class="pr-stat-value">{{ $bookingStats['total'] ?? 0 }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.completed') }}</p>
                    <p class="pr-stat-value pr-stat-green">{{ $bookingStats['completed'] ?? 0 }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.cancelled') }}</p>
                    <p class="pr-stat-value pr-stat-red">{{ $bookingStats['cancelled'] ?? 0 }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.cancellation_rate') }}</p>
                    <p class="pr-stat-value pr-stat-amber">{{ $bookingStats['cancellation_rate'] ?? 0 }}%</p>
                </div>
            </div>

            {{-- KPI Stats Row 2 --}}
            <div class="pr-grid-6" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.cash_revenue') }}</p>
                    <p class="pr-stat-value pr-stat-value-sm">&euro;{{ number_format($revenueStats['cash'] ?? 0, 2) }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.card_revenue') }}</p>
                    <p class="pr-stat-value pr-stat-value-sm">&euro;{{ number_format($revenueStats['card'] ?? 0, 2) }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.daily_avg') }}</p>
                    <p class="pr-stat-value pr-stat-value-sm">&euro;{{ number_format($dailyAvgRevenue, 2) }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.pending') }}</p>
                    <p class="pr-stat-value pr-stat-value-sm pr-stat-yellow">{{ $bookingStats['pending'] ?? 0 }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.no_show') }}</p>
                    <p class="pr-stat-value pr-stat-value-sm pr-stat-red">{{ $bookingStats['no_show'] ?? 0 }}</p>
                </div>
                <div class="pr-card">
                    <p class="pr-stat-label">{{ __('provider_report.stats.avg_duration') }}</p>
                    <p class="pr-stat-value pr-stat-value-sm">{{ round($avgServiceDuration) }} {{ __('provider_report.minutes') }}</p>
                </div>
            </div>

            {{-- Charts Row 1: Revenue & Bookings Over Time --}}
            <div class="pr-grid-2" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.charts.revenue_over_time') }}</h3>
                    <div class="pr-chart-container">
                        <canvas x-ref="revenueChart"></canvas>
                    </div>
                </div>
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.charts.bookings_over_time') }}</h3>
                    <div class="pr-chart-container">
                        <canvas x-ref="bookingsChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Charts Row 2: Peak Hours & Peak Days --}}
            <div class="pr-grid-2" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.charts.peak_hours') }}</h3>
                    <div class="pr-chart-container">
                        <canvas x-ref="peakHoursChart"></canvas>
                    </div>
                </div>
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.charts.peak_days') }}</h3>
                    <div class="pr-chart-container">
                        <canvas x-ref="peakDaysChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Charts Row 3: Service Revenue Pie & Service Breakdown Table --}}
            <div class="pr-grid-2" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.charts.service_revenue_distribution') }}</h3>
                    <div class="pr-chart-container" style="display: flex; align-items: center; justify-content: center;">
                        <canvas x-ref="serviceRevenuePieChart"></canvas>
                    </div>
                </div>
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.tables.service_performance') }}</h3>
                    <div style="overflow-x: auto;">
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>{{ __('provider_report.tables.service') }}</th>
                                    <th>{{ __('provider_report.tables.count') }}</th>
                                    <th>{{ __('provider_report.tables.revenue') }}</th>
                                    <th>{{ __('provider_report.tables.avg_duration') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($serviceBreakdown as $sb)
                                    <tr>
                                        <td class="font-medium">{{ $sb['name'] }}</td>
                                        <td>{{ $sb['count'] }}</td>
                                        <td>&euro;{{ number_format($sb['revenue'], 2) }}</td>
                                        <td>{{ $sb['avg_duration'] }} {{ __('provider_report.minutes') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('provider_report.no_data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Tables Row: Top Customers & Work Schedule --}}
            <div class="pr-grid-2" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.tables.top_customers') }}</h3>
                    <div style="overflow-x: auto;">
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('provider_report.tables.customer') }}</th>
                                    <th>{{ __('provider_report.tables.bookings') }}</th>
                                    <th>{{ __('provider_report.tables.total_spent') }}</th>
                                    <th>{{ __('provider_report.tables.last_visit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topCustomers as $i => $tc)
                                    <tr>
                                        <td style="color: #9ca3af;">{{ $i + 1 }}</td>
                                        <td class="font-medium">{{ $tc['name'] }}</td>
                                        <td>{{ $tc['bookings'] }}</td>
                                        <td>&euro;{{ number_format($tc['spent'], 2) }}</td>
                                        <td>{{ $tc['last_visit'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('provider_report.no_data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.tables.work_schedule') }}</h3>
                    @if(!empty($workSchedule['schedule']))
                        <div style="margin-bottom: 0.75rem; display: flex; gap: 1.5rem; font-size: 0.8rem; color: #6b7280;">
                            <span>{{ __('provider_report.work_days') }}: <strong style="color: #111827;">{{ $workSchedule['work_days_count'] }}</strong></span>
                            <span>{{ __('provider_report.weekly_hours') }}: <strong style="color: #111827;">{{ $workSchedule['weekly_hours'] }}h</strong></span>
                        </div>
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>{{ __('provider_report.tables.day') }}</th>
                                    <th>{{ __('provider_report.tables.start') }}</th>
                                    <th>{{ __('provider_report.tables.end') }}</th>
                                    <th>{{ __('provider_report.tables.hours') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($workSchedule['schedule'] as $ws)
                                    <tr>
                                        <td class="font-medium">{{ $ws['day'] }}</td>
                                        <td>{{ $ws['start'] }}</td>
                                        <td>{{ $ws['end'] }}</td>
                                        <td>{{ $ws['hours'] }}h</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('provider_report.no_schedule') }}</p>
                    @endif
                </div>
            </div>

            {{-- Tables Row: Recent Appointments & Time Off --}}
            <div class="pr-grid-2" style="margin-bottom: 1.5rem;">
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.tables.recent_appointments') }}</h3>
                    <div style="overflow-x: auto;">
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>{{ __('provider_report.tables.date') }}</th>
                                    <th>{{ __('provider_report.tables.time') }}</th>
                                    <th>{{ __('provider_report.tables.customer') }}</th>
                                    <th>{{ __('provider_report.tables.duration_col') }}</th>
                                    <th>{{ __('provider_report.tables.amount') }}</th>
                                    <th>{{ __('provider_report.tables.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAppointments as $ra)
                                    <tr>
                                        <td>{{ $ra['date'] }}</td>
                                        <td>{{ $ra['time'] }}</td>
                                        <td class="font-medium">{{ $ra['customer'] }}</td>
                                        <td>{{ $ra['duration'] }} {{ __('provider_report.minutes') }}</td>
                                        <td>&euro;{{ number_format($ra['amount'], 2) }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($ra['status_color']) {
                                                    'green' => 'pr-badge-green',
                                                    'red' => 'pr-badge-red',
                                                    'amber' => 'pr-badge-amber',
                                                    default => 'pr-badge-gray',
                                                };
                                            @endphp
                                            <span class="pr-badge {{ $badgeClass }}">{{ __('provider_report.status.' . $ra['status_key']) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('provider_report.no_data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="pr-card">
                    <h3 class="pr-section-title">{{ __('provider_report.tables.time_off') }}</h3>
                    @if(!empty($timeOffSummary['records']))
                        <div style="margin-bottom: 0.75rem; display: flex; gap: 1.5rem; font-size: 0.8rem; color: #6b7280;">
                            <span>{{ __('provider_report.total_days_off') }}: <strong style="color: #111827;">{{ $timeOffSummary['total_days'] }}d</strong></span>
                            <span>{{ __('provider_report.total_hours_off') }}: <strong style="color: #111827;">{{ $timeOffSummary['total_hours'] }}h</strong></span>
                            <span>{{ __('provider_report.total_records') }}: <strong style="color: #111827;">{{ $timeOffSummary['count'] }}</strong></span>
                        </div>
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>{{ __('provider_report.tables.date') }}</th>
                                    <th>{{ __('provider_report.tables.type') }}</th>
                                    <th>{{ __('provider_report.tables.duration_col') }}</th>
                                    <th>{{ __('provider_report.tables.reason') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($timeOffSummary['records'] as $to)
                                    <tr>
                                        <td>{{ $to['start'] }}{{ $to['end'] !== $to['start'] ? ' - ' . $to['end'] : '' }}</td>
                                        <td>
                                            <span class="pr-badge {{ $to['type'] === 'hourly' ? 'pr-badge-amber' : 'pr-badge-gray' }}">
                                                {{ __('provider_report.time_off_type.' . $to['type']) }}
                                            </span>
                                        </td>
                                        <td>{{ $to['duration'] }}</td>
                                        <td>{{ $to['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('provider_report.no_time_off') }}</p>
                    @endif
                </div>
            </div>
        @else
            <div class="pr-card" style="text-align: center; padding: 3rem;">
                <p style="font-size: 1.1rem; color: #6b7280;">{{ __('provider_report.select_provider_prompt') }}</p>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        function providerReportPage() {
            return {
                charts: {},

                init() {
                    this.$nextTick(() => this.renderAll());

                    this.$wire.$watch('revenueOverTime', () => {
                        this.$nextTick(() => this.renderAll());
                    });
                },

                destroyAll() {
                    Object.values(this.charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                    this.charts = {};
                },

                renderAll() {
                    this.destroyAll();
                    this.renderRevenueChart(this.$wire.revenueOverTime || []);
                    this.renderBookingsChart(this.$wire.bookingsOverTime || []);
                    this.renderPeakHoursChart(this.$wire.peakHours || []);
                    this.renderPeakDaysChart(this.$wire.peakDays || []);
                    this.renderServiceRevenuePie(this.$wire.serviceRevenuePie || []);
                },

                colors: [
                    '#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6',
                    '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'
                ],

                renderRevenueChart(data) {
                    const ctx = this.$refs.revenueChart;
                    if (!ctx || !data.length) return;
                    this.charts.revenue = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.map(d => d.period),
                            datasets: [{
                                label: '{{ __("provider_report.stats.total_revenue") }}',
                                data: data.map(d => d.revenue),
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                fill: true, tension: 0.4, pointRadius: 4,
                                pointBackgroundColor: '#f59e0b',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { callback: v => '\u20AC' + v } } }
                        }
                    });
                },

                renderBookingsChart(data) {
                    const ctx = this.$refs.bookingsChart;
                    if (!ctx || !data.length) return;
                    this.charts.bookings = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.period),
                            datasets: [
                                { label: '{{ __("provider_report.stats.completed") }}', data: data.map(d => d.completed), backgroundColor: '#10b981', borderRadius: 4 },
                                { label: '{{ __("provider_report.stats.cancelled") }}', data: data.map(d => d.cancelled), backgroundColor: '#ef4444', borderRadius: 4 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'top' } },
                            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                },

                renderPeakHoursChart(data) {
                    const ctx = this.$refs.peakHoursChart;
                    if (!ctx || !data.length) return;
                    const max = Math.max(...data.map(x => x.count));
                    this.charts.peakHours = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.hour),
                            datasets: [{
                                label: '{{ __("provider_report.tables.bookings") }}',
                                data: data.map(d => d.count),
                                backgroundColor: data.map(d => `rgba(245, 158, 11, ${0.3 + (d.count / (max||1)) * 0.7})`),
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                },

                renderPeakDaysChart(data) {
                    const ctx = this.$refs.peakDaysChart;
                    if (!ctx || !data.length) return;
                    this.charts.peakDays = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.day),
                            datasets: [{
                                label: '{{ __("provider_report.tables.bookings") }}',
                                data: data.map(d => d.count),
                                backgroundColor: this.colors.slice(0, data.length),
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                },

                renderServiceRevenuePie(data) {
                    const ctx = this.$refs.serviceRevenuePieChart;
                    if (!ctx || !data.length) return;
                    this.charts.serviceRevenuePie = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.map(d => d.name),
                            datasets: [{
                                data: data.map(d => d.revenue),
                                backgroundColor: this.colors.slice(0, data.length),
                                borderWidth: 2,
                                borderColor: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { boxWidth: 12, padding: 12 } },
                                tooltip: { callbacks: { label: (c) => c.label + ': \u20AC' + c.parsed.toFixed(2) } }
                            }
                        }
                    });
                },
            };
        }
    </script>
</x-filament-panels::page>
