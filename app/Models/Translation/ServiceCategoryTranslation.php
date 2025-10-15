<?php

namespace App\Models\Translation;

use App\Models\Language;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ServiceCategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_category_id',
        'language_id',
        'language_code',
        'name',
        'description',
    ];

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
