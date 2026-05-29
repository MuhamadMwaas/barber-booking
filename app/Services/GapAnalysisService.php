<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ProviderTimeOff;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GapAnalysisService
 *
 * Pure analysis layer — never mutates the database. Determines whether
 * a new service can be inserted before/after an existing appointment,
 * and if not, returns enough information so the UI can offer the user:
 *   - Reduce duration
 *   - Push subsequent bookings
 *   - Cancel
 *
 * Operates in three modes:
 *   1) analyzeAddBefore  — same provider, BEFORE the anchor
 *   2) analyzeAddAfter   — same provider, AFTER the anchor (may require push)
 *   3) analyzeChildAdd   — DIFFERENT provider (will become a child appointment)
 */
class GapAnalysisService
{
    /** Maximum allowed gap (in minutes) between the new service and the anchor's boundary. */
    public const MAX_GAP_MINUTES = 60;

    /**
     * Analyze inserting a new service BEFORE the anchor (same provider).
     *
     * @return array Shape:
     *  - is_possible: bool
     *  - reason: string|null         (if not possible)
     *  - max_duration_available: int (minutes between previous-end and anchor-start)
     *  - requires_reduction: bool    (true when requested duration exceeds the gap)
     *  - requires_push: false        (we never push BACKWARDS — bookings before anchor are not shifted)
     *  - suggested_start_time: ?string ('H:i')
     *  - suggested_end_time: ?string ('H:i')
     *  - gap_minutes: int            (gap between new-end and anchor-start)
     */
    public function analyzeAddBefore(
        Appointment $anchor,
        Service $service,
        int $requestedDuration,
        ?Carbon $requestedStartTime = null
    ): array {
        $provider = $anchor->provider;
        $date = $anchor->appointment_date->format('Y-m-d');
        $anchorStart = $anchor->start_time->copy();

        // 1) Provider schedule for the day
        $schedule = $this->getProviderSchedule($provider->id, $date);
        if (! $schedule) {
            return ['is_possible' => false, 'reason' => 'provider_not_working'];
        }
        $workStart = Carbon::parse($date . ' ' . $schedule->start_time);
        $workEnd   = Carbon::parse($date . ' ' . $schedule->end_time);

        // 2) Full-day time off check
        if ($this->hasFullDayTimeOff($provider->id, $date)) {
            return ['is_possible' => false, 'reason' => 'provider_full_day_off'];
        }

        // 3) Previous appointment (same provider, same day, ends ≤ anchor.start_time, NOT self)
        $previous = Appointment::query()
            ->where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->where('id', '!=', $anchor->id)
            ->where('end_time', '<=', $anchorStart)
            ->orderBy('end_time', 'desc')
            ->first();

        // 4) Earliest possible start = max(previous.end_time, workStart)
        $earliestStart = $previous
            ? $previous->end_time->copy()
            : $workStart->copy();

        // Clamp by current time when the date is today (cannot book in the past)
        if ($anchor->appointment_date->isToday() && $earliestStart->lt(Carbon::now())) {
            $earliestStart = Carbon::now();
        }

        // 5) Max duration available before the anchor
        $maxDuration = max(0, $earliestStart->diffInMinutes($anchorStart));

        if ($maxDuration === 0) {
            return [
                'is_possible' => false,
                'reason' => 'no_space_before',
                'max_duration_available' => 0,
                'requires_reduction' => true,
            ];
        }

        if ($requestedDuration > $maxDuration) {
            return [
                'is_possible' => false,
                'reason' => 'insufficient_space',
                'max_duration_available' => $maxDuration,
                'requires_reduction' => true,
            ];
        }

        // 6) Compute proposed times
        if ($requestedStartTime) {
            $proposedStart = $requestedStartTime->copy();
            $proposedEnd   = $proposedStart->copy()->addMinutes($requestedDuration);

            if ($previous && $proposedStart->lt($previous->end_time)) {
                return ['is_possible' => false, 'reason' => 'overlaps_previous'];
            }
            if ($proposedEnd->gt($anchorStart)) {
                return ['is_possible' => false, 'reason' => 'overlaps_anchor'];
            }
        } else {
            // Default placement: back-to-back with the anchor's start.
            $proposedEnd   = $anchorStart->copy();
            $proposedStart = $proposedEnd->copy()->subMinutes($requestedDuration);
            // If that would land before the earliest available, clamp.
            if ($proposedStart->lt($earliestStart)) {
                $proposedStart = $earliestStart->copy();
                $proposedEnd   = $proposedStart->copy()->addMinutes($requestedDuration);
            }
        }

        // 7) Check hourly time-off conflicts for the new slot
        if ($this->hasHourlyTimeOffConflict($provider->id, $date, $proposedStart, $proposedEnd)) {
            return ['is_possible' => false, 'reason' => 'provider_time_off_conflict'];
        }

        // 8) Gap check: gap = (anchor.start_time - new_service.end_time)
        $gap = $proposedEnd->diffInMinutes($anchorStart);
        if ($gap > self::MAX_GAP_MINUTES) {
            return [
                'is_possible' => false,
                'reason' => 'gap_too_large',
                'gap_minutes' => $gap,
                'max_gap_allowed' => self::MAX_GAP_MINUTES,
            ];
        }

        // 9) Must end before work end (defensive — should be guaranteed since anchor itself is inside hours)
        if ($proposedEnd->gt($workEnd)) {
            return ['is_possible' => false, 'reason' => 'exceeds_work_hours'];
        }

        return [
            'is_possible' => true,
            'reason' => null,
            'max_duration_available' => $maxDuration,
            'requires_reduction' => false,
            'requires_push' => false,
            'suggested_start_time' => $proposedStart->format('H:i'),
            'suggested_end_time' => $proposedEnd->format('H:i'),
            'gap_minutes' => $gap,
        ];
    }

    /**
     * Analyze inserting a new service AFTER the anchor (same provider).
     * May require a cascading push.
     */
    public function analyzeAddAfter(
        Appointment $anchor,
        Service $service,
        int $requestedDuration,
        ?Carbon $requestedStartTime = null
    ): array {
        $provider = $anchor->provider;
        $date = $anchor->appointment_date->format('Y-m-d');
        $anchorEnd = $anchor->end_time->copy();

        // 1) Schedule
        $schedule = $this->getProviderSchedule($provider->id, $date);
        if (! $schedule) {
            return ['is_possible' => false, 'reason' => 'provider_not_working'];
        }
        $workEnd = Carbon::parse($date . ' ' . $schedule->end_time);

        // 2) Full-day off
        if ($this->hasFullDayTimeOff($provider->id, $date)) {
            return ['is_possible' => false, 'reason' => 'provider_full_day_off'];
        }

        // 3) Determine proposed times
        $proposedStart = $requestedStartTime ? $requestedStartTime->copy() : $anchorEnd->copy();
        $proposedEnd   = $proposedStart->copy()->addMinutes($requestedDuration);

        // 4) Gap to anchor (anchor.end → new.start)
        $gap = $anchorEnd->diffInMinutes($proposedStart);
        if ($gap > self::MAX_GAP_MINUTES) {
            return [
                'is_possible' => false,
                'reason' => 'gap_too_large',
                'gap_minutes' => $gap,
                'max_gap_allowed' => self::MAX_GAP_MINUTES,
            ];
        }
        if ($proposedStart->lt($anchorEnd)) {
            return ['is_possible' => false, 'reason' => 'overlaps_anchor'];
        }

        // 5) Work-hours check
        if ($proposedEnd->gt($workEnd)) {
            $maxDur = max(0, $proposedStart->diffInMinutes($workEnd));
            return [
                'is_possible' => false,
                'reason' => 'exceeds_work_hours',
                'max_duration_available' => $maxDur,
                'requires_reduction' => true,
            ];
        }

        // 6) Hourly time off check
        if ($this->hasHourlyTimeOffConflict($provider->id, $date, $proposedStart, $proposedEnd)) {
            return ['is_possible' => false, 'reason' => 'provider_time_off_conflict'];
        }

        // 7) Next appointment for this provider after the anchor
        $next = Appointment::query()
            ->where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->where('id', '!=', $anchor->id)
            ->where('start_time', '>=', $anchorEnd)
            ->orderBy('start_time', 'asc')
            ->first();

        if (! $next) {
            // Fits cleanly until end of day
            $maxDur = $proposedStart->diffInMinutes($workEnd);
            return [
                'is_possible' => true,
                'requires_push' => false,
                'requires_reduction' => false,
                'suggested_start_time' => $proposedStart->format('H:i'),
                'suggested_end_time' => $proposedEnd->format('H:i'),
                'gap_minutes' => $gap,
                'max_duration_available' => $maxDur,
            ];
        }

        // 8) Fits before the next appointment?
        if ($proposedEnd->lte($next->start_time)) {
            $maxDur = $proposedStart->diffInMinutes($next->start_time);
            return [
                'is_possible' => true,
                'requires_push' => false,
                'requires_reduction' => false,
                'suggested_start_time' => $proposedStart->format('H:i'),
                'suggested_end_time' => $proposedEnd->format('H:i'),
                'gap_minutes' => $gap,
                'max_duration_available' => $maxDur,
            ];
        }

        // 9) PUSH REQUIRED — plan it
        $pushService = app(PushBookingsService::class);
        $pushPlan = $pushService->planPushFrom(
            $provider,
            $proposedEnd,
            $next,
            $anchor->id
        );

        $maxDur = max(0, $proposedStart->diffInMinutes($next->start_time));

        if (! $pushPlan['is_possible']) {
            return [
                'is_possible' => false,
                'reason' => $pushPlan['reason'],
                'push_blocked_by_paid' => $pushPlan['reason'] === 'paid_booking_in_chain',
                'push_blocked_by_hours' => $pushPlan['reason'] === 'exceeds_work_hours',
                'blocking_appointment_number' => $pushPlan['blocking_appointment_number'] ?? null,
                'max_duration_available' => $maxDur,
                'requires_reduction' => true,
            ];
        }

        return [
            'is_possible' => true,
            'requires_push' => true,
            'requires_reduction' => false,
            'suggested_start_time' => $proposedStart->format('H:i'),
            'suggested_end_time' => $proposedEnd->format('H:i'),
            'gap_minutes' => $gap,
            'max_duration_available' => $maxDur,
            'push_plan' => $pushPlan['plan'],
        ];
    }

    /**
     * Analyze adding a service via a DIFFERENT provider — produces a CHILD appointment.
     *
     * Boundary reference: the invoiceOwner's start_time / end_time, with the same
     * 60-min gap rule.
     * Conflict reference: the NEW provider's schedule & bookings.
     *
     * Push is NEVER applied for child placement (no chain to push — different provider).
     */
    public function analyzeChildAdd(
        Appointment $invoiceOwner,
        User $newProvider,
        Service $service,
        int $requestedDuration,
        string $placement,
        ?Carbon $requestedStartTime = null
    ): array {
        $date = $invoiceOwner->appointment_date->format('Y-m-d');
        $ownerStart = $invoiceOwner->start_time->copy();
        $ownerEnd = $invoiceOwner->end_time->copy();

        // 1) New provider schedule
        $schedule = $this->getProviderSchedule($newProvider->id, $date);
        if (! $schedule) {
            return ['is_possible' => false, 'reason' => 'provider_not_working'];
        }
        $workStart = Carbon::parse($date . ' ' . $schedule->start_time);
        $workEnd   = Carbon::parse($date . ' ' . $schedule->end_time);

        // 2) Full-day off
        if ($this->hasFullDayTimeOff($newProvider->id, $date)) {
            return ['is_possible' => false, 'reason' => 'provider_full_day_off'];
        }

        // 3) Default placement
        if ($requestedStartTime) {
            $proposedStart = $requestedStartTime->copy();
            $proposedEnd   = $proposedStart->copy()->addMinutes($requestedDuration);
        } else {
            if ($placement === 'before') {
                $proposedEnd   = $ownerStart->copy();
                $proposedStart = $proposedEnd->copy()->subMinutes($requestedDuration);
            } else {
                $proposedStart = $ownerEnd->copy();
                $proposedEnd   = $proposedStart->copy()->addMinutes($requestedDuration);
            }
        }

        // 4) Gap-to-owner check
        if ($placement === 'before') {
            $gap = $proposedEnd->diffInMinutes($ownerStart);
            if ($proposedEnd->gt($ownerStart)) {
                return ['is_possible' => false, 'reason' => 'overlaps_owner'];
            }
        } else {
            $gap = $ownerEnd->diffInMinutes($proposedStart);
            if ($proposedStart->lt($ownerEnd)) {
                return ['is_possible' => false, 'reason' => 'overlaps_owner'];
            }
        }
        if ($gap > self::MAX_GAP_MINUTES) {
            return [
                'is_possible' => false,
                'reason' => 'gap_too_large',
                'gap_minutes' => $gap,
                'max_gap_allowed' => self::MAX_GAP_MINUTES,
            ];
        }

        // 5) Work-hours bounds (new provider)
        if ($proposedStart->lt($workStart) || $proposedEnd->gt($workEnd)) {
            return ['is_possible' => false, 'reason' => 'exceeds_work_hours'];
        }

        // 6) Past time check
        if ($proposedStart->lt(Carbon::now()) && $invoiceOwner->appointment_date->isToday()) {
            return ['is_possible' => false, 'reason' => 'in_past'];
        }

        // 7) Hourly time off conflict
        if ($this->hasHourlyTimeOffConflict($newProvider->id, $date, $proposedStart, $proposedEnd)) {
            return ['is_possible' => false, 'reason' => 'provider_time_off_conflict'];
        }

        // 8) Appointment conflict with NEW provider
        $hasConflict = Appointment::query()
            ->where('provider_id', $newProvider->id)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->where(function ($q) use ($proposedStart, $proposedEnd) {
                $q->where('start_time', '<', $proposedEnd)
                  ->where('end_time', '>', $proposedStart);
            })
            ->exists();

        if ($hasConflict) {
            return ['is_possible' => false, 'reason' => 'new_provider_busy'];
        }

        return [
            'is_possible' => true,
            'requires_push' => false,
            'requires_reduction' => false,
            'suggested_start_time' => $proposedStart->format('H:i'),
            'suggested_end_time' => $proposedEnd->format('H:i'),
            'gap_minutes' => $gap,
            'mode' => 'child',
        ];
    }

    // ============================================================
    // Internal helpers
    // ============================================================

    /**
     * @return object|null { start_time, end_time }
     */
    protected function getProviderSchedule(int $providerId, string $date)
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        return DB::table('provider_scheduled_works')
            ->where('user_id', $providerId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();
    }

    protected function hasFullDayTimeOff(int $providerId, string $date): bool
    {
        return ProviderTimeOff::query()
            ->where('user_id', $providerId)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    protected function hasHourlyTimeOffConflict(int $providerId, string $date, Carbon $start, Carbon $end): bool
    {
        return ProviderTimeOff::query()
            ->where('user_id', $providerId)
            ->where('type', ProviderTimeOff::TYPE_HOURLY)
            ->whereDate('start_date', $date)
            ->whereRaw("TIME(start_time) < ?", [$end->format('H:i:s')])
            ->whereRaw("TIME(end_time) > ?", [$start->format('H:i:s')])
            ->exists();
    }
}
