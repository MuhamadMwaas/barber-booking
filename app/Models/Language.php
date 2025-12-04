<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{


    protected $fillable = [
        'name',
        'native_name',
        'code',
        'order',
        'is_active',
        'is_default'
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'order' => 'integer'
    ];
}
