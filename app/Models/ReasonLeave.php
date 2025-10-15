<?php

namespace App\Models;

use App\Models\Translation\ReasonLeaveTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReasonLeave extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function timeOffs()
    {
        return $this->hasMany(ProviderTimeOff::class, 'reason_id');
    }
    public function translations(): HasMany
    {
        return $this->hasMany(ReasonLeaveTranslation::class);
    }

    public function translation(string $locale): ?ReasonLeaveTranslation
    {
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            $language = Language::where('is_default', true)->first();
        }

        return $this->translations()->where('language_id', $language->id)->first();
    }

    public function getNameIn(string $locale): string
    {
        return $this->translation($locale)?->name ?? $this->name;
    }

    public function getDescriptionIn(string $locale): ?string
    {
        return $this->translation($locale)?->description ?? $this->description;
    }

    public function translate(string $locale, array $attributes): ReasonLeaveTranslation
    {
        $language = Language::where('code', $locale)->firstOrFail();

        return $this->translations()->updateOrCreate(
            ['reason_leave_id' => $this->id, 'language_id' => $language->id],
            $attributes
        );
    }
}
