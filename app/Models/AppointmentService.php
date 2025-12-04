<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentService extends Model
{
    use HasFactory;
    protected $table = 'appointment_services';

    protected $fillable = [
        'appointment_id',
        'service_id',
        'service_name',
        'duration_minutes',
        'price',
        'sequence_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'decimal:2',
        'sequence_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


        protected static function boot()
    {
        parent::boot();

        static::creating(function ($AppointmentService) {
            if ( is_null($AppointmentService->service_name)) {
                $service = Service::find($AppointmentService->service_id);
                if ($service) {
                    $AppointmentService->service_name = $service->name;
                }

            }
        });
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }


    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }


    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence_order', 'asc');
    }


    public function scopeForAppointment($query, int $appointmentId)
    {
        return $query->where('appointment_id', $appointmentId);
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }


    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }
}
