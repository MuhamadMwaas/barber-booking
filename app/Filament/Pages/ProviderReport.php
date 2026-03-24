<?php

namespace App\Filament\Pages;

use App\Services\ProviderReportService;
use App\Traits\NavigationDefaultAccess;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ProviderReport extends Page {
    use NavigationDefaultAccess;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;
    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.reports');
    }
    protected string $view = 'filament.pages.provider-report';

    public string $dateFrom;
    public string $dateTo;
    public string $activePreset = 'month';
    public ?int $selectedProviderId = null;

    public array $providers = [];
    public array $providerInfo = [];
    public array $revenueStats = [];
    public array $bookingStats = [];
    public array $revenueOverTime = [];
    public array $bookingsOverTime = [];
    public array $peakHours = [];
    public array $peakDays = [];
    public array $serviceBreakdown = [];
    public array $serviceRevenuePie = [];
    public array $topCustomers = [];
    public array $recentAppointments = [];
    public array $workSchedule = [];
    public array $timeOffSummary = [];
    public array $utilization = [];
    public float $avgServiceDuration = 0;
    public float $dailyAvgRevenue = 0;

    public static function getNavigationLabel(): string {
        return __('provider_report.title');
    }

    public function getTitle(): string {
        return __('provider_report.title');
    }

    public function mount(): void {
        $this->dateTo = Carbon::today()->format('Y-m-d');
        $this->dateFrom = Carbon::today()->subMonth()->format('Y-m-d');

        $service = app(ProviderReportService::class);
        $this->providers = $service->getProviderList();

        if (count($this->providers) > 0) {
            $this->selectedProviderId = $this->providers[0]['id'];
            $this->loadReport();
        }
    }

    public function setPreset(string $preset): void {
        $this->activePreset = $preset;
        $this->dateTo = Carbon::today()->format('Y-m-d');

        $this->dateFrom = match ($preset) {
            'week' => Carbon::today()->subWeek()->format('Y-m-d'),
            'month' => Carbon::today()->subMonth()->format('Y-m-d'),
            'year' => Carbon::today()->subYear()->format('Y-m-d'),
            'all' => '2020-01-01',
            default => Carbon::today()->subMonth()->format('Y-m-d'),
        };

        $this->loadReport();
    }

    public function updatedDateFrom(): void {
        $this->activePreset = 'custom';
        $this->loadReport();
    }

    public function updatedDateTo(): void {
        $this->activePreset = 'custom';
        $this->loadReport();
    }

    public function updatedSelectedProviderId(): void {
        $this->loadReport();
    }

    public function loadReport(): void {
        if (!$this->selectedProviderId) {
            $this->resetData();
            return;
        }

        $service = app(ProviderReportService::class);
        $id = $this->selectedProviderId;
        $from = $this->dateFrom;
        $to = $this->dateTo;

        $this->providerInfo = $service->getProviderInfo($id);
        $this->revenueStats = $service->getRevenueStats($id, $from, $to);
        $this->bookingStats = $service->getBookingStats($id, $from, $to);
        $this->revenueOverTime = $service->getRevenueOverTime($id, $from, $to);
        $this->bookingsOverTime = $service->getBookingsOverTime($id, $from, $to);
        $this->peakHours = $service->getPeakHours($id, $from, $to);
        $this->peakDays = $service->getPeakDays($id, $from, $to);
        $this->serviceBreakdown = $service->getServiceBreakdown($id, $from, $to);
        $this->serviceRevenuePie = $service->getServiceRevenuePie($id, $from, $to);
        $this->topCustomers = $service->getTopCustomers($id, $from, $to);
        $this->recentAppointments = $service->getRecentAppointments($id, $from, $to);
        $this->workSchedule = $service->getWorkScheduleSummary($id);
        $this->timeOffSummary = $service->getTimeOffSummary($id, $from, $to);
        $this->utilization = $service->getUtilization($id, $from, $to);
        $this->avgServiceDuration = $service->getAvgServiceDuration($id, $from, $to);
        $this->dailyAvgRevenue = $service->getDailyAvgRevenue($id, $from, $to);
    }

    private function resetData(): void {
        $this->providerInfo = [];
        $this->revenueStats = [];
        $this->bookingStats = [];
        $this->revenueOverTime = [];
        $this->bookingsOverTime = [];
        $this->peakHours = [];
        $this->peakDays = [];
        $this->serviceBreakdown = [];
        $this->serviceRevenuePie = [];
        $this->topCustomers = [];
        $this->recentAppointments = [];
        $this->workSchedule = [];
        $this->timeOffSummary = [];
        $this->utilization = [];
        $this->avgServiceDuration = 0;
        $this->dailyAvgRevenue = 0;
    }
}
