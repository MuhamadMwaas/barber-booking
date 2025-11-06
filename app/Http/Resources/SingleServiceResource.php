<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SingleServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Returns complete service details with all relationships.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'display_price' => $this->display_price,
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatted_duration,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'image_url' => $this->image_url,
            'color_code' => $this->color_code,
            'icon_url' => $this->icon_url,
            'is_featured' => $this->is_featured,
            'has_discount' => $this->discount_price !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Statistics
            'statistics' => [
                'average_rating' => $this->when(
                    $this->relationLoaded('reviews'),
                    fn() => round($this->reviews->avg('rating') ?? 0, 1)
                ),
                'review_count' => $this->when(
                    $this->relationLoaded('reviews'),
                    fn() => $this->reviews->count()
                ),
                'provider_count' => $this->when(
                    $this->relationLoaded('providers'),
                    fn() => $this->providers->count()
                ),
            ],


            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'providers' => ProviderResource::collection($this->whenLoaded('providers')),
            'reviews' => ServiceReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
