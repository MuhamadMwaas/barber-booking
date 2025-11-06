<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MinCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Returns minimal category data (id and name only).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}