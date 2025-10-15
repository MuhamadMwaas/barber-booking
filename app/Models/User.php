<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enum\AppointmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens,HasRoles;


    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar_url',
        'address',
        'phone',
        'google_id',
        'user_type',
        'city',
        'notes',
        'locale',
        'is_active',
        'branch_id'
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function scheduledWorks(): HasMany
    {
        return $this->hasMany(ProviderScheduledWork::class, 'user_id');
    }

    public function timeOffs(): HasMany
    {
        return $this->hasMany(ProviderTimeOff::class, 'user_id');
    }

    public function customerAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function appointmentsAsProvider()
    {
        return $this->hasMany(Appointment::class, 'provider_id');
    }

      public function appointmentsFinshedAsProvider()
    {
        return $this->hasMany(Appointment::class, 'provider_id')->whereNotIn('status', [ AppointmentStatus::ADMIN_CANCELLED, AppointmentStatus::USER_CANCELLED]);
    }

    public function serviceReviews()
    {
        return $this->hasMany(ServiceReview::class);
    }



    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'provider_service', 'provider_id', 'service_id')
            // ->withPivot('is_active', 'custom_price', 'custom_duration', 'notes')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
