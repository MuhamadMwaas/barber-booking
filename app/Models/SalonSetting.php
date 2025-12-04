<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
protected $casts = [
    'value' => 'json',
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



    // protected function value(): Attribute
    // {
    //     return Attribute::make(
    //         get: function ($value) {
    //             return match ($this->type) {
    //                 self::TYPE_INTEGER => intval($value),
    //                 self::TYPE_DECIMAL => floatval($value),
    //                 self::TYPE_JSON => json_decode($value, true),
    //                 default => $value,
    //             };
    //         },
    //     );
    // }

}
