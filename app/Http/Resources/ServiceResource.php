<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // relationships
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'providers' => ProviderResource::collection($this->whenLoaded('providers')),
            'reviews' => ServiceReviewResource::collection($this->whenLoaded('reviews')),

            'has_discount' => $this->discount_price !== null,
        ];
    }
}
