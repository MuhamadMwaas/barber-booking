<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderTimeOff extends Model
{
    use HasFactory;
    protected $table = 'provider_time_offs';

    const TYPE_HOURLY = 0;
    const TYPE_FULL_DAY = 1;
    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'duration_hours',
        'duration_days',
        'reason_id',
    ];

    protected $casts = [
        'type' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'duration_hours' => 'decimal:2',
        'duration_days' => 'integer',
    ];

    // relationships

    public function provider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonLeave::class, 'reason_id');
    }
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now()->toDateString());
    }


    public function scopePast($query)
    {
        return $query->where('end_date', '<', now()->toDateString());
    }

    public function scopeOverlapping($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }


    /**
     * Leaves whose date range covers $date.
     *
     * THE single definition of "this leave applies on that day", shared by the
     * availability layer and the booking layer. They used to carry two different
     * conditions — availability COALESCE'd a null end_date and honoured the whole
     * range, while booking required a non-null end_date for full-day leaves and
     * looked only at start_date for hourly ones — so an extended leave hid slots
     * from the customer while the booking call happily accepted them (BOOK-04).
     *
     * A null end_date means a single-day leave: the row covers start_date only.
     */
    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $date)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date]);
    }

    /**
     * The slice of $date this leave actually occupies, or null if it does not
     * touch that day at all.
     *
     * An hourly leave spanning several days is ONE CONTINUOUS ABSENCE, not the
     * same window repeated every morning: 10 Sep 12:00 → 15 Sep 13:00 means the
     * provider is gone from noon on the 10th until 13:00 on the 15th, so the days
     * in between are fully blocked.
     *
     *   start day  → start_time until end of day
     *   middle day → the whole day
     *   end day    → beginning of day until end_time
     *   single day → start_time until end_time (start day and end day at once)
     *
     * A full-day leave has no times and simply occupies every day it covers.
     * Rows with a half-filled time pair are treated as covering nothing rather
     * than silently blocking from midnight — bad data must not quietly close the
     * calendar.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function blockedWindowOn(Carbon $date): ?array
    {
        $day = $date->copy()->startOfDay();
        $startDate = Carbon::parse($this->start_date)->startOfDay();
        $endDate = Carbon::parse($this->end_date ?? $this->start_date)->startOfDay();

        if ($day->lt($startDate) || $day->gt($endDate)) {
            return null;
        }

        if ($this->isFullDay()) {
            return ['start' => $day->copy(), 'end' => $day->copy()->addDay()];
        }

        // An hourly row with a half-filled time pair is malformed. Blocking from
        // midnight would silently close the provider's whole calendar, so treat
        // it as covering nothing and let the input validation catch it instead.
        if ($this->start_time === null || $this->end_time === null) {
            return null;
        }

        return [
            'start' => $day->isSameDay($startDate)
                ? $this->combine($day, $this->start_time)
                : $day->copy(),
            'end' => $day->isSameDay($endDate)
                ? $this->combine($day, $this->end_time)
                : $day->copy()->addDay(),
        ];
    }

    /**
     * Does this leave overlap [$windowStart, $windowEnd)?
     *
     * Half-open on both sides, so a slot starting exactly when the leave ends is
     * bookable — the same convention Appointment::scopeOverlapping() uses.
     */
    public function blocksWindow(Carbon $windowStart, Carbon $windowEnd): bool
    {
        $blocked = $this->blockedWindowOn($windowStart);

        if ($blocked === null) {
            return false;
        }

        return $blocked['start']->lt($windowEnd) && $blocked['end']->gt($windowStart);
    }

    /**
     * `start_time` / `end_time` are cast to Carbon, so concatenating them onto a
     * date string throws a "double date specification" parse error. Always take
     * the time part explicitly.
     */
    private function combine(Carbon $day, $time): Carbon
    {
        $timeString = $time instanceof \DateTimeInterface
            ? $time->format('H:i:s')
            : (string) $time;

        return Carbon::parse($day->format('Y-m-d').' '.$timeString);
    }

    public function isHourly(): bool
    {
        return $this->type === self::TYPE_HOURLY;
    }


    public function isFullDay(): bool
    {
        return $this->type === self::TYPE_FULL_DAY;
    }

    public function isUpcoming(): bool
    {
        return $this->start_date > now()->toDateString();
    }

    public function isPast(): bool
    {
        return $this->end_date < now()->toDateString();
    }
}
