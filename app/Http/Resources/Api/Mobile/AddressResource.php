<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'region_code' => $this->region_code,
            'region_name' => $this->region_name,
            'province_code' => $this->province_code,
            'province_name' => $this->province_name,
            'municipality_code' => $this->municipality_code,
            'municipality_name' => $this->municipality_name,
            'barangay_code' => $this->barangay_code,
            'barangay_name' => $this->barangay_name,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,
            'is_default' => (bool) $this->is_default,
        ];
    }
}

