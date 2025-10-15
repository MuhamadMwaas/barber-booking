<?php

namespace App\Models\Translation;

use App\Models\Language;
use App\Models\ReasonLeave;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReasonLeaveTranslation extends Model
{
    protected $fillable = [
        'reason_leave_id',
        'language_id',
        'language_code',
        'name',
        'description',
    ];


    public function reasonLeave(): BelongsTo
    {
        return $this->belongsTo(ReasonLeave::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

}
