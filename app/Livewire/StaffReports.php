<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithDashboardPermissions;
use App\Livewire\Concerns\ProvidesDashboardChrome;
use App\Services\DailyReportService;
use Carbon\Carbon;
use Livewire\Component;

/**
 * StaffReports — the "Reports" dashboard tab.
 *
 * Deliberately thin, like StaffStats: it only holds the filter state and a live
 * preview, then hands off to the printable document at
 * `staff.dashboard.report.print`, which re-derives everything from
 * DailyReportService. That means the printed report is never a snapshot of
 * component state — the URL alone fully describes it, so it can be bookmarked,
 * shared with the accountant, or reprinted later and still be identical.
 *
 * Access needs `StaffDashboard:view_reports` on top of the route middleware's
 * `StaffDashboard:access`, because the report exposes every employee's takings.
 */
class StaffReports extends Component
{
    use ProvidesDashboardChrome;
    use InteractsWithDashboardPermissions;

    /** 'day' = a single date, 'range' = from..to. */
    public string $mode = 'day';

    public string $fromDate = '';

    public string $toDate = '';

    /** Empty = every provider. */
    public array $selectedProviderIds = [];

    /** Mirrors DailyReportController::MAX_RANGE_DAYS. */
    private const MAX_RANGE_DAYS = 366;

    protected DailyReportService $reports;

    public function boot(DailyReportService $reports): void
    {
        $this->reports = $reports;
    }

    public function mount(): void
    {
        abort_unless($this->dashCan('view_reports'), 403);

        $today = Carbon::today()->format('Y-m-d');
        $this->fromDate = $today;
        $this->toDate = $today;
    }

    /**
     * In single-day mode the two dates move together, so switching to range
     * mode never starts from an accidentally inverted window.
     */
    public function updatedFromDate(): void
    {
        if ($this->mode === 'day') {
            $this->toDate = $this->fromDate;
        }
    }

    public function updatedMode(): void
    {
        if ($this->mode === 'day') {
            $this->toDate = $this->fromDate;
        }
    }

    /** Preset windows. Anything multi-day flips the component into range mode. */
    public function applyPreset(string $preset): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($preset) {
            'yesterday'  => [$today->copy()->subDay(), $today->copy()->subDay()],
            'this_week'  => [$today->copy()->startOfWeek(), $today->copy()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default      => [$today->copy(), $today->copy()],
        };

        $this->fromDate = $from->format('Y-m-d');
        $this->toDate = $to->format('Y-m-d');
        $this->mode = $this->fromDate === $this->toDate ? 'day' : 'range';
    }

    public function selectAllProviders(): void
    {
        $this->selectedProviderIds = array_column($this->reports->providerOptions(), 'id');
    }

    public function clearProviders(): void
    {
        $this->selectedProviderIds = [];
    }

    /**
     * The effective window, with a reversed range swapped rather than treated as
     * empty. Returns Y-m-d strings.
     *
     * @return array{0:string,1:string}
     */
    public function resolvedRange(): array
    {
        $from = $this->fromDate ?: Carbon::today()->format('Y-m-d');
        $to = $this->mode === 'day' ? $from : ($this->toDate ?: $from);

        return Carbon::parse($from)->gt(Carbon::parse($to)) ? [$to, $from] : [$from, $to];
    }

    /** Null when the current selection is valid, otherwise the reason. */
    public function rangeError(): ?string
    {
        [$from, $to] = $this->resolvedRange();

        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        if ($days > self::MAX_RANGE_DAYS) {
            return __('z_report.range_too_long', ['days' => self::MAX_RANGE_DAYS]);
        }

        return null;
    }

    public function render()
    {
        abort_unless($this->dashCan('view_reports'), 403);

        [$from, $to] = $this->resolvedRange();
        $error = $this->rangeError();

        // Skip the (potentially heavy) preview build when the range is invalid —
        // the view shows the error instead.
        $preview = $error === null
            ? $this->reports->build($from, $to, $this->selectedProviderIds)
            : null;

        return view('livewire.staff-reports', [
            'providers'       => $this->reports->providerOptions(),
            'preview'         => $preview,
            'rangeError'      => $error,
            'reportUrl'       => route('staff.dashboard.report.print', array_filter([
                'from'      => $from,
                'to'        => $to,
                'providers' => $this->selectedProviderIds ?: null,
            ])),
            'activeLanguages' => $this->getActiveLanguages(),
        ])->layout('layouts.dashboard');
    }
}
