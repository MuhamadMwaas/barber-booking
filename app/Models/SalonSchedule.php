<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
