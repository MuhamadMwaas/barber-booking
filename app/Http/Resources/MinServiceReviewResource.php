<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MinServiceReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Returns minimal review data (rating only).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
        ];
    }
}
