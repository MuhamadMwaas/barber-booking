<?php

namespace App\Models;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'email',
        'phone',
        'otp',
        'expires_at',
        'device',
        'type',
        'purpose',
        'attempts',
        'used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
        'type' => OtpType::class,
        'purpose' => OtpPurpose::class,
        'attempts' => 'integer',
    ];

    protected $attributes = [
        'purpose' => OtpPurpose::ACCOUNT_VERIFICATION->value,
        'attempts' => 0,
    ];
}
