<?php

namespace App\Models;

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
