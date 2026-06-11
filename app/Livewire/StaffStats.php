<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithDashboardPermissions;
use App\Livewire\Concerns\ProvidesDashboardChrome;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Livewire\Component;

/**
 * StaffStats — the "Daily Statistics" dashboard tab.
 *
 * Deliberately thin: it holds only the selected day + scope and delegates ALL
 * number-crunching to DashboardStatsService (isolated logic). Two scopes:
 *
 *   - Provider (no `view_team`) → sees ONLY their own day: completed / in-progress
 *     now / upcoming / paid revenue / cancelled / source split / services / hours.
 *   - Admin / manager (or a provider WITH `view_team`) → sees the salon-wide
 *     totals for the day PLUS a per-provider breakdown.
 *
 * Access is gated by the `StaffDashboard:view_stats` ability on top of the
 * route-level `StaffDashboard:access` middleware.
 */
class StaffStats extends Component
{
    use ProvidesDashboardChrome;
    use InteractsWithDashboardPermissions;

    /** The day being inspected (Y-m-d). Defaults to today. */
    public string $selectedDate;

    protected DashboardStatsService $statsService;

    public function boot(DashboardStatsService $statsService): void
    {
        $this->statsService = $statsService;
    }

    public function mount(): void
    {
        // Hard stop: the tab is hidden without this ability, but never rely on a
        // hidden link as the only line of defence.
        abort_unless($this->dashCan('view_stats'), 403);

        $this->selectedDate = Carbon::today()->format('Y-m-d');
    }

    public function goToToday(): void
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
    }

    /**
     * Salon scope = admin/manager, or a provider who may also see the team.
     * A pure provider is locked to their own column (cannot be widened client-side).
     */
    public function isSalonScope(): bool
    {
        if (! $this->isCurrentUserProvider()) {
            return true;
        }

        return $this->dashCan('view_team');
    }

    public function render()
    {
        abort_unless($this->dashCan('view_stats'), 403);

        $salonScope = $this->isSalonScope();

        // The provider's own column id when scoped to "my stats".
        $scopeProviderId = $salonScope ? null : $this->currentProviderId();

        $stats = $this->statsService->statsForDate($this->selectedDate, $scopeProviderId);
        $breakdown = $salonScope
            ? $this->statsService->perProviderBreakdown($this->selectedDate)
            : [];

        $today = Carbon::today()->format('Y-m-d');

        return view('livewire.staff-stats', [
            'stats' => $stats,
            'breakdown' => $breakdown,
            'isSalonScope' => $salonScope,
            'isToday' => $this->selectedDate === $today,
            'today' => $today,
            'activeLanguages' => $this->getActiveLanguages(),
        ])->layout('layouts.dashboard');
    }
}
