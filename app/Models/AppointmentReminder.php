<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminder extends Model
{
    protected $fillable = [
        'appointment_id',
        'user_id',
        'remind_at',
        'status',
        'cancelled_at',
        'sent_at',
        'job_uuid',
        'locale',
        'title_key',
        'message_key',
        'params',
        'data',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sent_at' => 'datetime',
        'params' => 'array',
        'data' => 'array',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
