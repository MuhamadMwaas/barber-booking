<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A compact view of one other block of the same booking (BOOKING-GAP-01).
 *
 * Deliberately NOT AppointmentResource: that one renders `linked_appointments`
 * itself, and nesting it would recurse and repeat the full payload per block.
 * The app only needs enough here to draw "also part of this booking".
 */
class LinkedAppointmentResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'time_range' => $this->time_range,
            'duration_minutes' => $this->duration_minutes,
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status->name,
            'status_value' => $this->status->value,
            'provider' => $this->whenLoaded('provider', fn () => [
                'id' => $this->provider->id,
                'full_name' => $this->provider->full_name,
            ]),
            'services_details' => $this->when(
                $this->relationLoaded('services_record'),
                fn () => AppointmentServiceResource::collection($this->services_record)
            ),
        ];
    }
}
