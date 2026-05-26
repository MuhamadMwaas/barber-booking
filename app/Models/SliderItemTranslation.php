<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SliderItemTranslation extends Model
{
    protected $table = 'slider_item_translations';

    protected $fillable = [
        'slider_item_id',
        'language_id',
        'title',
        'subtitle',
        'description',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(SliderItem::class, 'slider_item_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
