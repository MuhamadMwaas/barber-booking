<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class AppointmentServiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_name' => $this->service_name,
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatted_duration,
            'price' => (float)$this->price,
            'formatted_price' => $this->formatted_price,
            'sequence_order' => $this->sequence_order,

            'service' => $this->whenLoaded('service', fn() => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'image_url' => $this->service->image_url,
                'color_code' => $this->service->color_code,

            ]),
        ];
    }
}
