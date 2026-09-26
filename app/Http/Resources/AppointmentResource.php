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
                'profile_image_url' => $this->provider->profile_image_url,


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

            /*
             * Linked booking (BOOKING-GAP-01). A booking whose services are not
             * back-to-back at one provider is stored as one appointment per
             * block: the earliest block is the root, the others its children,
             * and every block appears in the lists as its own card. These keys
             * are additive — nothing above changed shape — so an app that does
             * not know them keeps working.
             *
             *  - group_root_id: the same value on every block of one booking.
             *  - linked_appointments / group_total_amount: on the ROOT only, and
             *    only when its children are loaded (the create response and the
             *    detail endpoints) — never in the lists, so no query per card.
             *    A child's own `children` is always empty, so on a child these
             *    would claim a one-block group; they are omitted instead.
             */
            'parent_appointment_id' => $this->parent_appointment_id,
            'group_root_id' => $this->group_root_id,
            'is_child_booking' => $this->parent_appointment_id !== null,
            'linked_appointments' => $this->when(
                $this->parent_appointment_id === null && $this->relationLoaded('children'),
                fn () => LinkedAppointmentResource::collection($this->children->sortBy('start_time')->values())
            ),
            'group_total_amount' => $this->when(
                $this->parent_appointment_id === null && $this->relationLoaded('children'),
                fn () => (float) collect([$this->resource])
                    ->merge($this->children)
                    ->filter(fn ($member) => $member->isActiveInGroup())
                    ->sum('total_amount')
            ),

            'booking_source' => $this->booking_source?->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            /*
             * The live reminder, so the booking screen can render its toggle and
             * dropdown from the same payload it already fetches.
             *
             * `whenLoaded` on purpose: callers that do not eager-load
             * `activeReminder` omit the key entirely instead of firing one query
             * per appointment down a list. A loaded-but-empty relation yields
             * null, which is the app's "toggle is off".
             */
            'reminder' => $this->whenLoaded(
                'activeReminder',
                fn () => $this->activeReminder
                    ? new AppointmentReminderResource($this->activeReminder)
                    : null
            ),

            'is_upcoming' => $this->start_time > now(),
            'is_past' => $this->start_time < now(),
            'is_cancelled' => in_array($this->status->value, [-1, -2]),
            'is_completed' => $this->status->value === 1,
            'can_cancel' => $this->status->value === 0 && $this->start_time > now(),
        ];
    }
}
