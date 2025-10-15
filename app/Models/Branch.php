<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $table = 'branchs';


    protected $fillable = [
        'name',
        'adress',
        'phone',
        'email',
        'latitude',
        'longitude',
        'is_active',
    ];


    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function schedules()
    {
        return $this->hasMany(SalonSchedule::class, 'branch_id');
    }

    public function settings()
    {
        return $this->hasMany(SalonSetting::class, 'branch_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
}
