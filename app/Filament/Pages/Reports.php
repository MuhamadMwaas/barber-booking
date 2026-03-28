<?php

namespace App\Filament\Pages;

use App\Services\ReportsService;
use App\Traits\NavigationDefaultAccess;
use Carbon\Carbon;
use Filament\Panel;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Reports extends Page {
    use NavigationDefaultAccess;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;
    protected static ?int $navigationSort = 30;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.reports');
    }
    protected string $view = 'filament.pages.reports';

    public string $dateFrom;
    public string $dateTo;
    public string $activePreset = 'month';

    public array $revenueStats = [];
    public array $bookingStats = [];
    public array $topProvidersByRevenue = [];
    public array $topProvidersByServices = [];
    public array $mostRequestedServices = [];
    public array $topRevenueServices = [];
    public array $topCustomers = [];
    public array $revenueOverTime = [];
    public array $bookingsOverTime = [];
    public array $peakHours = [];
    public array $peakDays = [];
    public float $avgServiceDuration = 0;
    public array $providerUtilization = [];

    public static function getNavigationLabel(): string {
        return __('reports.title');
    }

    public function getTitle(): string {
        return __('reports.title');
    }

    public function mount(): void {
        $this->dateTo = Carbon::today()->format('Y-m-d');
        $this->dateFrom = Carbon::today()->subMonth()->format('Y-m-d');
        $this->loadReports();
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

        $this->loadReports();
    }

    public function updatedDateFrom(): void {
        $this->activePreset = 'custom';
        $this->loadReports();
    }

    public function updatedDateTo(): void {
        $this->activePreset = 'custom';
        $this->loadReports();
    }

    public function loadReports(): void {
        $service = app(ReportsService::class);

        $this->revenueStats = $service->getRevenueStats($this->dateFrom, $this->dateTo);
        $this->bookingStats = $service->getBookingStats($this->dateFrom, $this->dateTo);
        $this->topProvidersByRevenue = $service->getTopProvidersByRevenue($this->dateFrom, $this->dateTo);
        $this->topProvidersByServices = $service->getTopProvidersByServiceCount($this->dateFrom, $this->dateTo);
        $this->mostRequestedServices = $service->getMostRequestedServices($this->dateFrom, $this->dateTo);
        $this->topRevenueServices = $service->getTopRevenueServices($this->dateFrom, $this->dateTo);
        $this->topCustomers = $service->getTopCustomers($this->dateFrom, $this->dateTo);
        $this->revenueOverTime = $service->getRevenueOverTime($this->dateFrom, $this->dateTo);
        $this->bookingsOverTime = $service->getBookingsOverTime($this->dateFrom, $this->dateTo);
        $this->peakHours = $service->getPeakHours($this->dateFrom, $this->dateTo);
        $this->peakDays = $service->getPeakDays($this->dateFrom, $this->dateTo);
        $this->avgServiceDuration = $service->getAvgServiceDuration($this->dateFrom, $this->dateTo);
        $this->providerUtilization = $service->getProviderUtilization($this->dateFrom, $this->dateTo);
    }
}
