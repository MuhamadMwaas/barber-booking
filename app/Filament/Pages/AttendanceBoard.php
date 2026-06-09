<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AttendanceBoardService;
use App\Traits\NavigationDefaultAccess;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * Attendance Board — a card-grid review screen for provider attendance.
 *
 * Each card shows a provider, their most-recent attendance day as a timeline
 * line (actual attendance over the scheduled shift), and the last 3 days. Opening
 * a card reveals the full day-by-day history in an infinite-scroll modal.
 *
 * This is a read-only complement to {@see \App\Filament\Resources\ProviderAttendances\ProviderAttendanceResource}
 * (which stays as the row-level correction tool). Access is gated by its own
 * `AttendanceBoard:access` permission via {@see NavigationDefaultAccess} — so it
 * shows up as a toggleable tab on the Roles screen (seeded by PermissionsSeeder).
 */
class AttendanceBoard extends Page
{
    use NavigationDefaultAccess;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'team';

    protected static ?int $navigationSort = 26;

    protected string $view = 'filament.pages.attendance-board';

    /** Live name filter for the card grid. */
    public string $search = '';

    // ── History modal state ──────────────────────────────────────────────
    public ?int $selectedProviderId = null;

    public ?string $selectedProviderName = null;

    /** @var array<int, array<string, mixed>> appended day timelines (newest first) */
    public array $historyDays = [];

    public int $historyOffset = 0;

    public bool $historyHasMore = true;

    public int $historyPerPage = 10;

    public static function getNavigationLabel(): string
    {
        return __('attendance_board.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('attendance_board.title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('attendance_board.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('attendance_board.subheading');
    }

    /** Card payloads for the grid (recomputed each render so search stays live). */
    public function cards(): Collection
    {
        return app(AttendanceBoardService::class)->cards($this->search);
    }

    /** Open the history modal for a provider and load the first page. */
    public function openHistory(int $providerId): void
    {
        $provider = User::find($providerId);

        if (! $provider) {
            return;
        }

        $this->selectedProviderId   = $providerId;
        $this->selectedProviderName = $provider->full_name;
        $this->historyDays          = [];
        $this->historyOffset        = 0;
        $this->historyHasMore       = true;

        $this->loadMoreHistory();

        $this->dispatch('open-modal', id: 'attendance-history');
    }

    /** Append the next page of days (called by the IntersectionObserver sentinel). */
    public function loadMoreHistory(): void
    {
        if (! $this->selectedProviderId || ! $this->historyHasMore) {
            return;
        }

        $provider = User::find($this->selectedProviderId);

        if (! $provider) {
            $this->historyHasMore = false;

            return;
        }

        $result = app(AttendanceBoardService::class)
            ->history($provider, $this->historyOffset, $this->historyPerPage);

        $this->historyDays    = array_merge($this->historyDays, $result['days']);
        $this->historyOffset += count($result['days']);
        $this->historyHasMore = $result['has_more'];
    }
}
