<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enum\AppointmentStatus;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Filament\Panel;
use Filament\Models\Contracts\HasName;

class User extends Authenticatable implements FilamentUser, HasName
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
        'branch_id',
        'email_verified_via_otp_at',
        'email_verified_at'
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'profile_image_url',
        'full_name'
    ];
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_via_otp_at' => 'datetime',
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


    public function profile_image(): MorphOne {
        return $this->morphOne(File::class, 'fileable', 'instance_type', 'instance_id')->where('type', 'profile_image');
    }



    public function getProfileImageUrlAttribute(): ?string
    {
        if ($this->profile_image) {
            return $this->profile_image->urlFile();
        }
        return null;
    }

    /**
     * @param UploadedFile $image
     * @return File
     */
    public function updateProfileImage(UploadedFile $image): File
    {
        if ($this->profile_image) {
            $this->profile_image->delete();
        }

        $name = str_replace(' ', '_', trim($this->first_name));
        $extension = $image->getClientOriginalExtension();
        $dir = "users/profile_images/{$this->id}";
        $fileName = "{$name}_{$this->id}.{$extension}";
        $path = "{$dir}/{$fileName}";

        Storage::disk('public')->makeDirectory($dir);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $image->storeAs($dir, $fileName, 'public');

        return $this->profile_image()->create([
            'name'      => $name . '_' . $this->id,
            'path'      => $path,
            'disk'      => 'public',
            'type'      => 'profile_image',
            'extension' => $extension,
            'group'     => 'avatar',
            'key'       => 'profile',
        ]);
    }


    public function invoices(): HasMany
{
    return $this->hasMany(Invoice::class, 'customer_id');
}

public function savedPaymentMethods(): HasMany
{
    return $this->hasMany(SavePaymentMethod::class, 'user_id');
}

public function defaultPaymentMethod(): HasOne
{
    return $this->hasOne(SavePaymentMethod::class, 'user_id')->where('is_default', true);
}

public function getFilamentName(): string
{
    return $this->full_name;
}
}
