<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'number' => $this->number,

            'appointment_date' => $this->appointment_date->format('Y-m-d'),
            'formatted_date' => $this->formatted_date,
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'time_range' => $this->time_range,
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatted_duration,

            'subtotal' => (float)$this->subtotal,
            'tax_amount' => (float)$this->tax_amount,
            'total_amount' => (float)$this->total_amount,

            'status' => $this->status->name,
            'status_value' => $this->status->value,
            'status_label' => $this->status_label,
            'payment_status' => $this->payment_status->name,
            'payment_status_value' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status_label,
            'payment_method' => $this->payment_method,


            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),


            'provider' => [
                'id' => $this->provider->id,
                'full_name' => $this->provider->full_name,
                'email' => $this->provider->email,
                'phone' => $this->provider->phone,
                'avatar_url' => $this->provider->avatar_url,

            ],

            // 'customer' => $this->when($this->relationLoaded('customer'), [
            //     'id' => $this->customer->id,
            //     'first_name' => $this->customer->first_name,
            //     'last_name' => $this->customer->last_name,
            //     'full_name' => $this->customer->full_name,
            //     'email' => $this->customer->email,
            //     'phone' => $this->customer->phone,
            //     'avatar_url' => $this->customer->avatar_url,
            // ]),


            // 'services' => ServiceResource::collection($this->whenLoaded('services')),

            'services_details' => $this->when(
                $this->relationLoaded('services_record'),
                AppointmentServiceResource::collection($this->services_record)
            ),

            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            'is_upcoming' => $this->start_time > now(),
            'is_past' => $this->start_time < now(),
            'is_cancelled' => in_array($this->status->value, [-1, -2]),
            'is_completed' => $this->status->value === 1,
            'can_cancel' => $this->status->value === 0 && $this->start_time > now(),
        ];
    }
}
