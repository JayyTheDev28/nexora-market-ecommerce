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
        $fullName = trim((string) ($this->full_name ?: ($this->name ?? '')));
        if ($fullName === '' && ! empty($this->email)) {
            $fullName = explode('@', $this->email)[0];
        }
        if ($fullName === '') {
            $fullName = 'Buyer';
        }

        $parts = explode(' ', $fullName);
        $firstName = ! empty($this->first_name) ? $this->first_name : ($parts[0] ?? 'Buyer');
        $lastName = ! empty($this->last_name) ? $this->last_name : (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '');

        return [
            'id' => $this->id,
            'name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_initial' => $this->middle_initial,
            'full_name' => $fullName,
            'email' => $this->email,
            'phone' => $this->contact_number,
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

