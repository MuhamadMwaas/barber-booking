<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalonSetting extends Model
{
    use HasFactory;

    protected $table = 'salon_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'branch_id',
        'setting_group',
    ];

    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';
    const TYPE_DECIMAL = 'decimal';


    // =========================================
    // relationships

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
