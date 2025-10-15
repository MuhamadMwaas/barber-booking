<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'total_booking_count' => $this->appointmentsFinshedAsProvider()->count() ?? 0,

            'services' => $this->when(isset($this->services), function () {
                return $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'translated_name' => $service->translated_name ?? $service->name,
                        'display_price' => $service->display_price,
                        'is_active' => $service->is_active,
                        'image_url' => $service->image_url,
                        'is_featured'=> $this->is_featured,

                        'booking_count' => $service->booking_count ?? 0,
                        'average_rating' => $service->average_rating ?? 0,
                        'review_count' => $service->review_count ?? 0,


                    ];
                });
            }),

            'pivot' => $this->whenPivotLoaded('provider_service', function () {
                return [
                    'is_active' => $this->pivot->is_active,
                    'custom_price' => $this->pivot->custom_price,
                    'custom_duration' => $this->pivot->custom_duration,
                    'notes' => $this->pivot->notes,
                ];
            }),
        ];
    }
}
