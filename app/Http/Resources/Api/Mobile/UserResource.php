<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_initial' => $this->middle_initial,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'sex' => $this->sex,
            'birthday' => $this->birthday?->format('Y-m-d'),
            'age' => $this->age,
            'role' => $this->role,
            'approval_status' => $this->approval_status,
            'account_status' => $this->account_status,
            'email_verified' => $this->hasVerifiedEmail(),
            'avatar_url' => $this->avatar_url,
        ];
    }
}

