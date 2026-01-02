<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
class UserDevice extends Model
{

    protected $fillable = [
        'user_id',
        'device_id',
        'device_token',
        'platform',
        'os_version',
        'app_version',
        'is_active',
        'last_active_at',
        'meta'
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
        'meta' => 'array'
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class,'user_id','id');
    }


    /*
     * @return bool
     */
    public function updateLastActive(): bool
    {
        return $this->update(['last_active_at' => now()]);
    }



}
