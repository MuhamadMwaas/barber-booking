<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReview extends Model
{
    use HasFactory;

    protected $table = 'service_reviews';

    protected $fillable = [
        'service_id',
        'user_id',
        'rating',
        'comment',
        'is_approved',
    ];


    protected $casts = [
        'rating' => 'decimal:1',
        'is_approved' => 'boolean',
    ];

    // Relationships

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    // Scopes

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function getFormattedRatingAttribute(): string
    {
        $fullStars = floor($this->rating);
        $halfStar = ($this->rating - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - $fullStars - $halfStar;

        return str_repeat('★', $fullStars) .
            str_repeat('⯨', $halfStar) .
            str_repeat('☆', $emptyStars);
    }


    public function hasComment(): bool
    {
        return !empty($this->comment);
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}
