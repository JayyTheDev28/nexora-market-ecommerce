<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'is_online' => (bool) ($this->is_online ?? false),
            'vehicle_type' => $this->vehicle_type,
            'plate_number' => $this->plate_number,
            'license_number' => $this->license_number,
            'license_expiry_date' => $this->license_expiry_date?->format('Y-m-d'),
            'vehicle_verification_status' => $this->vehicle_verification_status,
            'sorting_center' => $this->sortingCenter?->full_name,
            'earnings_balance' => (float) ($this->earnings_balance ?? 0),
            'unremitted_cod_balance' => (float) ($this->unremitted_cod_balance ?? 0),
            'active_jobs_count' => method_exists($this->resource, 'activeJobsCount') ? $this->activeJobsCount() : 0,
        ];
    }
}

