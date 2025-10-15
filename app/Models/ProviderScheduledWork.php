<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderScheduledWork extends Model
{
    use HasFactory;
    protected $table = 'provider_scheduled_works';


    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_work_day',
        'break_minutes',
        'is_active',
    ];


    protected $casts = [
        'day_of_week' => 'integer',
        'is_work_day' => 'boolean',
        'break_minutes' => 'integer',
        'is_active' => 'boolean',

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

    public function provider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
