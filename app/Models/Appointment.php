<?php

namespace App\Models;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Appointment extends Model
{
    use HasFactory;


    protected $fillable = [
        'number',
        'customer_id',
        'provider_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'appointment_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'payment_method',
        'cancellation_reason',
        'cancelled_at',
        'notes',
        'payment_status',
        'created_status'
    ];


    protected $casts = [
        'appointment_date' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => AppointmentStatus::class,
        'payment_status' => PaymentStatus::class,
        'duration_minutes' => 'integer',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($appointment) {
            if (empty($appointment->number)) {
                $prefix = 'APT';
                $date = \Carbon\Carbon::now()->format('Ymd');
                $random = strtoupper(substr(uniqid(), -6));

                $appointment->number = "{$prefix}-{$date}-{$random}";
            }
        });
    }


    // Relationships

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->withPivot(['service_name', 'duration_minutes', 'price', 'sequence_order'])
            ->withTimestamps();
    }


    public function services_record(): HasMany
    {
        return $this->hasMany(AppointmentService::class, 'appointment_id');
    }

    // Accessors

    public function getStatusLabelAttribute()
    {
        return $this->status->getLabel();
    }

    public function getPaymentStatusLabelAttribute()
    {
        return $this->payment_status->label();
    }

    // Scopes

    public function scopeByStatus($query, AppointmentStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', AppointmentStatus::COMPLETED);
    }

    public function scopeByPaymentStatus($query, PaymentStatus $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where('start_time', '<', now());
    }


    public function complete(): bool
    {
        return $this->update(['status' => AppointmentStatus::COMPLETED]);
    }

    /**
     * Cancel the appointment
     */
    public function cancel(?string $reason = null): bool
    {
        return $this->update([
            'status' => AppointmentStatus::USER_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }



    public function getFormattedDateAttribute(): string
    {
        return $this->appointment_date->format('M d, Y');
    }


    public function getTimeRangeAttribute(): string
    {
        return $this->start_time->format('h:i A') . ' - ' . $this->end_time->format('h:i A');
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
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'appointment_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    public function getCustomerEmailAttribute()
    {
        return $this->customer ? $this->customer->email : $this->customer_email;
    }

    public function getCustomerPhoneAttribute()
    {
        return $this->customer ? $this->customer->phone : $this->customer_phone;
    }
    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->full_name
            ?? ($this->customer_name ?: 'Guest');
    }

    public function getHasCustomerAccountAttribute(): bool
    {
        return (bool) $this->customer_id;
    }



}
