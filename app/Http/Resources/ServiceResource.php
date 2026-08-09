<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->getNameIn($locale),
            'description' => $this->getDescriptionIn($locale),
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'average_rating' => $this->average_rating ? round((float) $this->average_rating, 1) : 0,

            // relationships
            'category' => new MinCategoryResource($this->whenLoaded('category')),
            // 'branch' => new BranchResource($this->whenLoaded('branch')),
            'providers' => MinProviderResource::collection($this->providers),
            // 'reviews' => MinServiceReviewResource::collection($this->whenLoaded('reviews')),

            'has_discount' => $this->discount_price !== null,
        ];
    }
}
