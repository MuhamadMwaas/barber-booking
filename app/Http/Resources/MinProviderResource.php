<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MinProviderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Returns minimal provider data (id, name, and avatar only).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'profile_image_url' => $this->profile_image_url,
        ];
    }
}
