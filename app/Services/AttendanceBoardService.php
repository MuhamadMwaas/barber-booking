<?php

namespace App\Services;

use App\Models\ProviderAttendance;
use App\Models\ProviderScheduledWork;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read/aggregation layer for the Attendance Board (the card-grid page).
 *
 * Responsibilities:
 *  - Shape one "card" per provider: live status + the most-recent day + last 3 days.
 *  - Paginate a provider's full day history for the infinite-scroll modal.
 *  - Turn raw sessions into a "timeline" — clamped percentages the Blade can paint
 *    directly: a faint SCHEDULED band (from ProviderScheduledWork) overlaid by the
 *    SOLID actual attendance bars. All time math lives here, never in the view.
 *
 * The board never blocks/writes attendance — that stays in {@see AttendanceService}.
 */
class AttendanceBoardService
{
    /** How many recent distinct days to surface on each card. */
    private const CARD_DAYS = 3;

    /** Only look back this far when building cards (full history lives in the modal). */
    private const CARD_LOOKBACK_DAYS = 180;

    /**
     * One card payload per provider, honoring an optional name search.
     *
     * Renders the whole board in ~3 queries (providers, schedules, recent
     * attendance); today's status is derived from the already-loaded rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(?string $search = null): Collection
    {
        $providers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'provider'))
            ->when(filled($search), function ($q) use ($search) {
                $term = '%' . trim($search) . '%';
                $q->where(fn ($w) => $w
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->with('branch')
            ->orderBy('first_name')
            ->get();

        if ($providers->isEmpty()) {
            return collect();
        }

        $ids = $providers->pluck('id')->all();

        $shifts = ProviderScheduledWork::whereIn('user_id', $ids)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->get()
            ->groupBy('user_id');

        $since = Carbon::today()->subDays(self::CARD_LOOKBACK_DAYS)->toDateString();
        $attendance = ProviderAttendance::whereIn('user_id', $ids)
            ->whereDate('work_date', '>=', $since)
            ->orderByDesc('work_date')
            ->orderBy('check_in_at')
            ->get()
            ->groupBy('user_id');

        $todayStr = Carbon::today()->toDateString();
        $todayDow = Carbon::today()->dayOfWeek;

        return $providers->map(function (User $provider) use ($shifts, $attendance, $todayStr, $todayDow) {
            $userShifts  = $shifts->get($provider->id, collect());
            $axis        = $this->axisFor($userShifts);
            $shiftsByDay = $userShifts->groupBy('day_of_week');

            $byDate = $attendance->get($provider->id, collect())
                ->groupBy(fn (ProviderAttendance $s) => $s->work_date->toDateString());

            $recentDays = $byDate->keys()
                ->take(self::CARD_DAYS)
                ->map(fn (string $date) => $this->dayTimeline(
                    $date,
                    $byDate->get($date),
                    $axis,
                    $shiftsByDay->get(Carbon::parse($date)->dayOfWeek, collect()),
                ))
                ->values();

            // Today's live status, derived from the loaded rows (no extra query).
            $todaySessions = $byDate->get($todayStr, collect());
            $openToday     = $todaySessions->firstWhere('check_out_at', null);
            $status        = $todaySessions->isEmpty() ? 'none' : ($openToday ? 'open' : 'closed');
            $lastOut       = $todaySessions->whereNotNull('check_out_at')
                ->sortByDesc('check_out_at')->first();

            return [
                'id'          => $provider->id,
                'name'        => $provider->full_name,
                'initial'     => mb_strtoupper(mb_substr(trim((string) $provider->full_name) ?: '?', 0, 1)),
                'branch'      => $provider->branch?->name,
                'status'      => $status,
                'is_work_day' => $shiftsByDay->has($todayDow),
                'open'        => $status === 'open',
                'since'       => $openToday?->check_in_at?->format('H:i'),
                'last_out'    => $lastOut?->check_out_at?->format('H:i'),
                'latest_day'  => $recentDays->first(),
                'recent_days' => $recentDays->all(),
                'has_any'     => $recentDays->isNotEmpty(),
            ];
        });
    }

    /**
     * A page of a single provider's history for the infinite-scroll modal,
     * newest day first. Paginated by DISTINCT work_date (one timeline per day).
     *
     * @return array{days: array<int, array<string, mixed>>, has_more: bool}
     */
    public function history(User $provider, int $offset, int $limit = 10): array
    {
        $shiftsByDay = ProviderScheduledWork::forUser($provider->id)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->get();

        $axis = $this->axisFor($shiftsByDay);
        $shiftsByDay = $shiftsByDay->groupBy('day_of_week');

        // Fetch one extra date to know whether more pages remain.
        $dates = ProviderAttendance::forUser($provider->id)
            ->select('work_date')
            ->distinct()
            ->orderByDesc('work_date')
            ->offset(max(0, $offset))
            ->limit($limit + 1)
            ->pluck('work_date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->values();

        $hasMore = $dates->count() > $limit;
        $dates   = $dates->take($limit);

        if ($dates->isEmpty()) {
            return ['days' => [], 'has_more' => false];
        }

        $sessions = ProviderAttendance::forUser($provider->id)
            ->whereIn('work_date', $dates->all())
            ->orderBy('check_in_at')
            ->get()
            ->groupBy(fn (ProviderAttendance $s) => $s->work_date->toDateString());

        $days = $dates->map(fn (string $date) => $this->dayTimeline(
            $date,
            $sessions->get($date, collect()),
            $axis,
            $shiftsByDay->get(Carbon::parse($date)->dayOfWeek, collect()),
        ))->all();

        return ['days' => $days, 'has_more' => $hasMore];
    }

    /**
     * Build one day's timeline: scheduled band(s) + actual session bar(s) as
     * clamped percentages on the shared provider axis, plus worked-time totals.
     *
     * @param  Collection<int, ProviderAttendance>     $sessions      sessions on this date
     * @param  array{start_min:int, end_min:int}       $axis
     * @param  Collection<int, ProviderScheduledWork>  $shiftsForDay  scheduled shifts for this weekday
     * @return array<string, mixed>
     */
    public function dayTimeline(string $date, Collection $sessions, array $axis, Collection $shiftsForDay): array
    {
        $span = max(1, $axis['end_min'] - $axis['start_min']);
        $now  = Carbon::now();

        // Scheduled band(s) — the faint background reference.
        $bands = $shiftsForDay
            ->filter(fn ($shift) => $shift->start_time && $shift->end_time)
            ->map(function ($shift) use ($axis, $span) {
                $start = ProviderScheduledWork::timeToMinutes($shift->start_time);
                $end   = ProviderScheduledWork::timeToMinutes($shift->end_time);
                if ($end <= $start) {
                    $end = 1440; // tolerate an overnight end; the axis clamps it
                }

                $left  = $this->clampPct(($start - $axis['start_min']) / $span * 100);
                $right = $this->clampPct(($end - $axis['start_min']) / $span * 100);

                return [
                    'left'  => round($left, 2),
                    'width' => round(max(0.5, $right - $left), 2),
                    'range' => ProviderScheduledWork::minutesToTime($start) . '–' . ProviderScheduledWork::minutesToTime(min($end, 1439)),
                ];
            })
            ->values()
            ->all();

        // Actual attendance bar(s) — the solid foreground.
        $totalMinutes = 0;
        $bars = [];
        foreach ($sessions as $session) {
            $startMin = $this->minuteOfDay($session->check_in_at);
            $isOpen   = $session->check_out_at === null;

            if ($isOpen) {
                // A live (today) open session grows to "now"; a forgotten one shows a marker.
                $endMin = $session->work_date->isToday()
                    ? max($startMin + 1, $this->minuteOfDay($now))
                    : $startMin + 1;
            } else {
                $endMin = $this->minuteOfDay($session->check_out_at);
                $totalMinutes += (int) $session->duration_minutes;
            }

            if ($endMin < $startMin) {
                $endMin = $startMin + 1;
            }

            $left  = $this->clampPct(($startMin - $axis['start_min']) / $span * 100);
            $right = $this->clampPct(($endMin - $axis['start_min']) / $span * 100);

            $bars[] = [
                'left'     => round($left, 2),
                'width'    => round(max(1.5, $right - $left), 2),
                'is_open'  => $isOpen,
                'in'       => $session->check_in_at->format('H:i'),
                'out'      => $isOpen ? null : $session->check_out_at->format('H:i'),
                'duration' => $isOpen ? null : $this->humanMinutes((int) $session->duration_minutes),
            ];
        }

        $lastOut = $sessions->whereNotNull('check_out_at')->sortByDesc('check_out_at')->first();

        return [
            'date'          => $date,
            'date_label'    => Carbon::parse($date)->translatedFormat('D d M'),
            'weekday_label' => Carbon::parse($date)->translatedFormat('l'),
            'has_records'   => $sessions->isNotEmpty(),
            'has_open'      => $sessions->contains(fn ($s) => $s->check_out_at === null),
            'sessions'      => $sessions->count(),
            'total_minutes' => $totalMinutes,
            'total_human'   => $this->humanMinutes($totalMinutes),
            'first_in'      => $sessions->first()?->check_in_at?->format('H:i'),
            'last_out'      => $lastOut?->check_out_at?->format('H:i'),
            'axis_start'    => ProviderScheduledWork::minutesToTime($axis['start_min']),
            'axis_end'      => ProviderScheduledWork::minutesToTime(min($axis['end_min'], 1439)),
            'bands'         => $bands,
            'bars'          => $bars,
        ];
    }

    /**
     * The time window (minutes-of-day) used for every line of a given provider so
     * bars line up vertically. Envelope of the scheduled shifts + 1h padding,
     * snapped to the hour; falls back to 06:00–22:00 when there is no schedule.
     *
     * @param  Collection<int, ProviderScheduledWork>  $shifts
     * @return array{start_min:int, end_min:int}
     */
    public function axisFor(Collection $shifts): array
    {
        $starts = [];
        $ends   = [];

        foreach ($shifts as $shift) {
            if (! $shift->start_time || ! $shift->end_time) {
                continue;
            }
            $start = ProviderScheduledWork::timeToMinutes($shift->start_time);
            $end   = ProviderScheduledWork::timeToMinutes($shift->end_time);
            $starts[] = $start;
            $ends[]   = $end <= $start ? 1440 : $end;
        }

        if (empty($starts)) {
            return ['start_min' => 6 * 60, 'end_min' => 22 * 60];
        }

        $start = intdiv(max(0, min($starts) - 60), 60) * 60;            // floor to hour
        $end   = (int) (ceil(min(1440, max($ends) + 60) / 60) * 60);    // ceil to hour

        if ($end <= $start) {
            return ['start_min' => 6 * 60, 'end_min' => 22 * 60];
        }

        return ['start_min' => $start, 'end_min' => $end];
    }

    private function minuteOfDay(Carbon $moment): int
    {
        return $moment->hour * 60 + $moment->minute;
    }

    private function clampPct(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    private function humanMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours   = intdiv($minutes, 60);
        $mins    = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return __('attendance_board.dur_hm', ['h' => $hours, 'm' => $mins]);
        }
        if ($hours > 0) {
            return __('attendance_board.dur_h', ['h' => $hours]);
        }

        return __('attendance_board.dur_m', ['m' => $mins]);
    }
}
