<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'registration_method' => $this->registration_method?->value ?? $this->registration_method,
            'address' => $this->address,
            'city' => $this->city,
            'avatar_url' => $this->profile_image_url ?? $this->avatar_url,
            'profile_image_url' => $this->profile_image_url,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'is_account_verified' => $this->is_account_verified,
            'requires_otp_verification' => $this->requires_otp_verification,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
