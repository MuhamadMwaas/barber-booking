<x-filament-panels::page>
    <style>
        .rpt-grid-6 { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; }
        .rpt-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
        .rpt-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .rpt-card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.05); padding: 1rem; }
        .dark .rpt-card { background: rgb(24, 24, 27); border-color: rgba(255,255,255,0.1); }
        .rpt-stat-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.25rem; }
        .dark .rpt-stat-label { color: #9ca3af; }
        .rpt-stat-value { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .dark .rpt-stat-value { color: white; }
        .rpt-stat-value-sm { font-size: 1.25rem; }
        .rpt-stat-green { color: #059669; }
        .rpt-stat-red { color: #dc2626; }
        .rpt-stat-amber { color: #d97706; }
        .rpt-stat-yellow { color: #ca8a04; }
        .rpt-section-title { font-size: 0.95rem; font-weight: 600; color: #111827; margin-bottom: 1rem; }
        .dark .rpt-section-title { color: white; }
        .rpt-chart-container { height: 280px; position: relative; }
        .rpt-table { width: 100%; font-size: 0.875rem; }
        .rpt-table th { text-align: start; padding: 0.5rem 0.75rem; font-weight: 500; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        .dark .rpt-table th { color: #9ca3af; border-color: #374151; }
        .rpt-table td { padding: 0.5rem 0.75rem; color: #374151; border-bottom: 1px solid #f3f4f6; }
        .dark .rpt-table td { color: #d1d5db; border-color: #1f2937; }
        .rpt-table td.font-medium { font-weight: 500; color: #111827; }
        .dark .rpt-table td.font-medium { color: white; }
        .rpt-preset-btn { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; transition: all 0.15s; cursor: pointer; border: none; }
        .rpt-preset-btn.active { background: #f59e0b; color: white; }
        .rpt-preset-btn.inactive { background: #f3f4f6; color: #374151; }
        .rpt-preset-btn.inactive:hover { background: #e5e7eb; }
        .dark .rpt-preset-btn.inactive { background: #374151; color: #d1d5db; }
        .rpt-date-input { border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.375rem 0.75rem; font-size: 0.875rem; }
        .dark .rpt-date-input { background: #374151; border-color: #4b5563; color: #d1d5db; }
        .rpt-progress-bg { width: 4rem; background: #e5e7eb; border-radius: 9999px; height: 0.5rem; }
        .dark .rpt-progress-bg { background: #374151; }
        .rpt-progress-bar { background: #f59e0b; height: 0.5rem; border-radius: 9999px; }
        .rpt-loading { position: fixed; inset: 0; background: rgba(0,0,0,0.2); z-index: 50; display: flex; align-items: center; justify-content: center; }
        .rpt-loading-box { background: white; border-radius: 0.75rem; padding: 1rem 1.5rem; box-shadow: 0 10px 15px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.75rem; }
        .dark .rpt-loading-box { background: #1f2937; }
        @media (max-width: 1024px) {
            .rpt-grid-6 { grid-template-columns: repeat(3, 1fr); }
            .rpt-grid-4 { grid-template-columns: repeat(2, 1fr); }
            .rpt-grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .rpt-grid-6 { grid-template-columns: repeat(2, 1fr); }
            .rpt-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
    </style>

    <div x-data="reportsPage()">

        {{-- Date Range Filter --}}
        <div class="rpt-card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <button wire:click="setPreset('week')" class="rpt-preset-btn {{ $activePreset === 'week' ? 'active' : 'inactive' }}">
                        {{ __('reports.presets.week') }}
                    </button>
                    <button wire:click="setPreset('month')" class="rpt-preset-btn {{ $activePreset === 'month' ? 'active' : 'inactive' }}">
                        {{ __('reports.presets.month') }}
                    </button>
                    <button wire:click="setPreset('year')" class="rpt-preset-btn {{ $activePreset === 'year' ? 'active' : 'inactive' }}">
                        {{ __('reports.presets.year') }}
                    </button>
                    <button wire:click="setPreset('all')" class="rpt-preset-btn {{ $activePreset === 'all' ? 'active' : 'inactive' }}">
                        {{ __('reports.presets.all') }}
                    </button>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-inline-start: auto;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <label style="font-size: 0.875rem; color: #6b7280;">{{ __('reports.from') }}</label>
                        <input type="date" wire:model.live.debounce.500ms="dateFrom" class="rpt-date-input">
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <label style="font-size: 0.875rem; color: #6b7280;">{{ __('reports.to') }}</label>
                        <input type="date" wire:model.live.debounce.500ms="dateTo" class="rpt-date-input">
                    </div>
                </div>
            </div>
        </div>

        {{-- Loading --}}
        <div wire:loading class="rpt-loading">
            <div class="rpt-loading-box">
                <svg style="animation: spin 1s linear infinite; height: 1.25rem; width: 1.25rem; color: #f59e0b;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">{{ __('reports.loading') }}</span>
            </div>
        </div>

        {{-- KPI Stats --}}
        <div class="rpt-grid-6" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.total_revenue') }}</p>
                <p class="rpt-stat-value">€{{ number_format($revenueStats['total'] ?? 0, 2) }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.avg_booking') }}</p>
                <p class="rpt-stat-value">€{{ number_format($revenueStats['average'] ?? 0, 2) }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.total_bookings') }}</p>
                <p class="rpt-stat-value">{{ $bookingStats['total'] ?? 0 }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.completed') }}</p>
                <p class="rpt-stat-value rpt-stat-green">{{ $bookingStats['completed'] ?? 0 }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.cancelled') }}</p>
                <p class="rpt-stat-value rpt-stat-red">{{ $bookingStats['cancelled'] ?? 0 }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.cancellation_rate') }}</p>
                <p class="rpt-stat-value rpt-stat-amber">{{ $bookingStats['cancellation_rate'] ?? 0 }}%</p>
            </div>
        </div>

        {{-- Secondary Stats --}}
        <div class="rpt-grid-4" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.cash_revenue') }}</p>
                <p class="rpt-stat-value rpt-stat-value-sm">€{{ number_format($revenueStats['cash'] ?? 0, 2) }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.card_revenue') }}</p>
                <p class="rpt-stat-value rpt-stat-value-sm">€{{ number_format($revenueStats['card'] ?? 0, 2) }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.pending') }}</p>
                <p class="rpt-stat-value rpt-stat-value-sm rpt-stat-yellow">{{ $bookingStats['pending'] ?? 0 }}</p>
            </div>
            <div class="rpt-card">
                <p class="rpt-stat-label">{{ __('reports.stats.avg_duration') }}</p>
                <p class="rpt-stat-value rpt-stat-value-sm">{{ round($avgServiceDuration) }} {{ __('reports.minutes') }}</p>
            </div>
        </div>

        {{-- Charts Row 1: Revenue & Bookings Over Time --}}
        <div class="rpt-grid-2" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.revenue_over_time') }}</h3>
                <div class="rpt-chart-container">
                    <canvas x-ref="revenueChart"></canvas>
                </div>
            </div>
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.bookings_over_time') }}</h3>
                <div class="rpt-chart-container">
                    <canvas x-ref="bookingsChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Charts Row 2: Peak Hours & Days --}}
        <div class="rpt-grid-2" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.peak_hours') }}</h3>
                <div class="rpt-chart-container">
                    <canvas x-ref="peakHoursChart"></canvas>
                </div>
            </div>
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.peak_days') }}</h3>
                <div class="rpt-chart-container">
                    <canvas x-ref="peakDaysChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Charts Row 3: Top Providers & Services Revenue --}}
        <div class="rpt-grid-2" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.top_providers_revenue') }}</h3>
                <div class="rpt-chart-container">
                    <canvas x-ref="providersRevenueChart"></canvas>
                </div>
            </div>
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.top_services_revenue') }}</h3>
                <div class="rpt-chart-container">
                    <canvas x-ref="servicesRevenueChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Charts Row 4: Most Requested Services & Provider Utilization --}}
        <div class="rpt-grid-2" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.charts.most_requested_services') }}</h3>
                <div class="rpt-chart-container" style="display: flex; align-items: center; justify-content: center;">
                    <canvas x-ref="requestedServicesChart"></canvas>
                </div>
            </div>
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.tables.provider_utilization') }}</h3>
                <div style="overflow-x: auto;">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>{{ __('reports.tables.provider') }}</th>
                                <th>{{ __('reports.tables.hours') }}</th>
                                <th>{{ __('reports.tables.appointments') }}</th>
                                <th>{{ __('reports.tables.utilization') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($providerUtilization as $pu)
                                <tr>
                                    <td class="font-medium">{{ $pu['name'] }}</td>
                                    <td>{{ $pu['hours'] }}h</td>
                                    <td>{{ $pu['appointments'] }}</td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div class="rpt-progress-bg">
                                                <div class="rpt-progress-bar" style="width: {{ min($pu['utilization'], 100) }}%"></div>
                                            </div>
                                            <span style="font-size: 0.75rem; color: #6b7280;">{{ $pu['utilization'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('reports.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tables Row: Top Providers by Services & Top Customers --}}
        <div class="rpt-grid-2" style="margin-bottom: 1.5rem;">
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.tables.top_providers_services') }}</h3>
                <div style="overflow-x: auto;">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('reports.tables.provider') }}</th>
                                <th>{{ __('reports.tables.services_count') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProvidersByServices as $i => $tp)
                                <tr>
                                    <td style="color: #9ca3af;">{{ $i + 1 }}</td>
                                    <td class="font-medium">{{ $tp['name'] }}</td>
                                    <td>{{ $tp['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('reports.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rpt-card">
                <h3 class="rpt-section-title">{{ __('reports.tables.top_customers') }}</h3>
                <div style="overflow-x: auto;">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('reports.tables.customer') }}</th>
                                <th>{{ __('reports.tables.bookings') }}</th>
                                <th>{{ __('reports.tables.total_spent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topCustomers as $i => $tc)
                                <tr>
                                    <td style="color: #9ca3af;">{{ $i + 1 }}</td>
                                    <td class="font-medium">{{ $tc['name'] }}</td>
                                    <td>{{ $tc['bookings'] }}</td>
                                    <td>€{{ number_format($tc['spent'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; padding: 1rem; color: #9ca3af;">{{ __('reports.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        function reportsPage() {
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
                    this.renderProvidersRevenueChart(this.$wire.topProvidersByRevenue || []);
                    this.renderServicesRevenueChart(this.$wire.topRevenueServices || []);
                    this.renderRequestedServicesChart(this.$wire.mostRequestedServices || []);
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
                                label: '{{ __("reports.stats.total_revenue") }}',
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
                            scales: { y: { beginAtZero: true, ticks: { callback: v => '€' + v } } }
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
                                { label: '{{ __("reports.stats.completed") }}', data: data.map(d => d.completed), backgroundColor: '#10b981', borderRadius: 4 },
                                { label: '{{ __("reports.stats.cancelled") }}', data: data.map(d => d.cancelled), backgroundColor: '#ef4444', borderRadius: 4 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'top' } },
                            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
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
                                label: '{{ __("reports.tables.bookings") }}',
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
                                label: '{{ __("reports.tables.bookings") }}',
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

                renderProvidersRevenueChart(data) {
                    const ctx = this.$refs.providersRevenueChart;
                    if (!ctx || !data.length) return;
                    this.charts.providersRevenue = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.name),
                            datasets: [{
                                label: '{{ __("reports.stats.total_revenue") }}',
                                data: data.map(d => d.revenue),
                                backgroundColor: this.colors.slice(0, data.length),
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true, ticks: { callback: v => '€' + v } } }
                        }
                    });
                },

                renderServicesRevenueChart(data) {
                    const ctx = this.$refs.servicesRevenueChart;
                    if (!ctx || !data.length) return;
                    this.charts.servicesRevenue = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.name),
                            datasets: [{
                                label: '{{ __("reports.stats.total_revenue") }}',
                                data: data.map(d => d.revenue),
                                backgroundColor: this.colors.slice(0, data.length),
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true, ticks: { callback: v => '€' + v } } }
                        }
                    });
                },

                renderRequestedServicesChart(data) {
                    const ctx = this.$refs.requestedServicesChart;
                    if (!ctx || !data.length) return;
                    this.charts.requestedServices = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.map(d => d.name),
                            datasets: [{
                                data: data.map(d => d.count),
                                backgroundColor: this.colors.slice(0, data.length),
                                borderWidth: 2, borderColor: '#fff',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } } }
                        }
                    });
                },
            };
        }
    </script>
</x-filament-panels::page>
