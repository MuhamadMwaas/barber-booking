<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SalonSchedule extends Model
{
    use HasFactory;

    protected $table = 'salon_schedules';



    protected $fillable = [
        'branch_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_open',
    ];


    protected $casts = [
        'day_of_week' => 'integer',
        'is_open' => 'boolean',

    ];
    const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // Relationships

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }


    public function scopeForDay($query, int $day)
    {
        return $query->where('day_of_week', $day);
    }


    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    /**
     * Is the salon closed on $dayOfWeek for a provider working at $branchId?
     *
     * THE rule, shared by booking validation and availability so the two cannot
     * disagree about a closed day:
     *
     *  - the provider's own branch row wins;
     *  - otherwise a row with a NULL branch_id is the salon-wide default;
     *  - no row at all means no restriction (the provider's own schedule decides).
     *
     * Only `is_open` is enforced. open_time/close_time stay informational: the
     * provider's shift already sets the hours, and clipping it here would make
     * slots vanish silently wherever a shift runs past the recorded salon hours.
     *
     * Pass $schedules (e.g. `SalonSchedule::all()`) to answer many days from one
     * query; without it the relevant rows are fetched.
     */
    public static function isClosedFor(?int $branchId, int $dayOfWeek, ?Collection $schedules = null): bool
    {
        $schedules ??= static::query()
            ->where('day_of_week', $dayOfWeek)
            ->where(fn ($query) => $query
                ->whereNull('branch_id')
                ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId)))
            ->get();

        $forDay = $schedules->where('day_of_week', $dayOfWeek);

        $row = ($branchId ? $forDay->firstWhere('branch_id', $branchId) : null)
            ?? $forDay->first(fn (self $schedule) => $schedule->branch_id === null);

        return $row !== null && ! $row->is_open;
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }


    public function getFormattedOpenTimeAttribute(): string
    {
        return date('h:i A', strtotime($this->open_time));
    }

    public function getFormattedCloseTimeAttribute(): string
    {
        return date('h:i A', strtotime($this->close_time));
    }

    public function getWorkingHoursAttribute(): string
    {
        if (!$this->is_open) {
            return 'Closed';
        }
        return "{$this->formatted_open_time} - {$this->formatted_close_time}";
    }
}
