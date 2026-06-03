<?php

namespace App\Services;

use App\Models\ProviderAttendance;
use App\Models\ProviderScheduledWork;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

/**
 * Provider attendance (work-time) logic.
 *
 * Rules:
 *  - Multiple sessions per day are allowed (split shifts / mid-day breaks).
 *  - A provider may not open a second session while one is still open *today*;
 *    they must check out first. A forgotten (open) session from a previous day
 *    does NOT block today's check-in — it just stays open ("no checkout").
 *  - Check-in/out outside the scheduled shift is allowed but flagged so the UI
 *    can warn the provider.
 *
 * The component talks to attendance only through this service.
 */
class AttendanceService
{
    /**
     * Open a new attendance session for the provider.
     *
     * @return array{attendance: ProviderAttendance, outside_shift: bool}
     *
     * @throws RuntimeException when an open session already exists for today.
     */
    public function checkIn(User $provider): array
    {
        $today = Carbon::today()->toDateString();

        $openToday = ProviderAttendance::forUser($provider->id)
            ->onDate($today)
            ->open()
            ->exists();

        if ($openToday) {
            throw new RuntimeException(__('dashboard.attendance.already_checked_in'));
        }

        $attendance = ProviderAttendance::create([
            'user_id'     => $provider->id,
            'branch_id'   => $provider->branch_id,
            'work_date'   => $today,
            'check_in_at' => now(),
            'source'      => 'dashboard',
        ]);

        return [
            'attendance'    => $attendance,
            'outside_shift' => ! $this->isWithinScheduledShift($provider, now()),
        ];
    }

    /**
     * Close the provider's most recent open session.
     *
     * @throws RuntimeException when there is no open session to close.
     */
    public function checkOut(User $provider): ProviderAttendance
    {
        $open = ProviderAttendance::forUser($provider->id)
            ->open()
            ->orderByDesc('check_in_at')
            ->first();

        if (! $open) {
            throw new RuntimeException(__('dashboard.attendance.no_open_session'));
        }

        $now = now();
        if ($now->lessThanOrEqualTo($open->check_in_at)) {
            // Guard against clock skew — never store a non-positive duration.
            $now = $open->check_in_at->copy()->addMinute();
        }

        $open->update(['check_out_at' => $now]);

        return $open->refresh();
    }

    /**
     * Snapshot of the provider's attendance for today, shaped for the UI.
     *
     * status: 'none' (not checked in) | 'open' (clocked in now) | 'closed'
     * (checked out at least once and nothing currently open).
     *
     * @return array{
     *   status: string, is_work_day: bool, sessions_count: int,
     *   since: ?string, last_out: ?string, open_id: ?int
     * }
     */
    public function todayState(User $provider): array
    {
        $today = Carbon::today()->toDateString();

        $sessions = ProviderAttendance::forUser($provider->id)
            ->onDate($today)
            ->orderBy('check_in_at')
            ->get();

        $open = $sessions->firstWhere('check_out_at', null);

        $status = 'none';
        if ($sessions->isNotEmpty()) {
            $status = $open ? 'open' : 'closed';
        }

        $lastClosed = $sessions
            ->whereNotNull('check_out_at')
            ->sortByDesc('check_out_at')
            ->first();

        return [
            'status'         => $status,
            'is_work_day'    => $this->isScheduledWorkDay($provider, $today),
            'sessions_count' => $sessions->count(),
            'since'          => $open?->check_in_at?->format('H:i'),
            'last_out'       => $lastClosed?->check_out_at?->format('H:i'),
            'open_id'        => $open?->id,
        ];
    }

    /** The provider's currently open session (most recent), or null. */
    public function openSession(User $provider): ?ProviderAttendance
    {
        return ProviderAttendance::forUser($provider->id)
            ->open()
            ->orderByDesc('check_in_at')
            ->first();
    }

    /** The provider's most recent completed (checked-out) session, or null. */
    public function lastCheckOut(User $provider): ?ProviderAttendance
    {
        return ProviderAttendance::forUser($provider->id)
            ->whereNotNull('check_out_at')
            ->orderByDesc('check_out_at')
            ->first();
    }

    /** The provider's last N sessions, newest first (for the history popup). */
    public function recentSessions(User $provider, int $limit = 30): \Illuminate\Support\Collection
    {
        return ProviderAttendance::forUser($provider->id)
            ->orderByDesc('check_in_at')
            ->limit($limit)
            ->get();
    }

    /** Does the provider have an active scheduled work day on the given date? */
    public function isScheduledWorkDay(User $provider, string $date): bool
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        return ProviderScheduledWork::where('user_id', $provider->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->exists();
    }

    /** Is the given moment inside the provider's scheduled shift for that day? */
    private function isWithinScheduledShift(User $provider, Carbon $moment): bool
    {
        $dayOfWeek = $moment->dayOfWeek;

        $schedule = ProviderScheduledWork::where('user_id', $provider->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();

        if (! $schedule || ! $schedule->start_time || ! $schedule->end_time) {
            return false;
        }

        $date  = $moment->toDateString();
        $start = Carbon::parse($date . ' ' . $schedule->start_time);
        $end   = Carbon::parse($date . ' ' . $schedule->end_time);

        return $moment->betweenIncluded($start, $end);
    }
}
