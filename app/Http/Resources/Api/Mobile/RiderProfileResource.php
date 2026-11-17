<?php

namespace App\Http\Resources\Api\Mobile;

use App\Models\Order;
use App\Models\Review;
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

        $riderId = $this->user_id;

        $reviewsQuery = Review::whereHas('order', fn ($q) => $q->where('delivery_courier_id', $riderId));
        $reviewsCount = $reviewsQuery->count();
        $avgRating = $reviewsCount > 0 ? (float) round($reviewsQuery->avg('rating'), 2) : 5.0;

        $totalDeliveries = Order::where('delivery_courier_id', $riderId)
            ->whereIn('status', ['delivered', 'completed'])
            ->count();

        $isCourier = $this->user?->isCourier() ?? false;
        $assignedZone = (! $isCourier && $this->user)
            ? $this->user->assignedZones()->with(['hub', 'barangays'])->first()
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'is_online' => (bool) ($this->is_online ?? false),
            'vehicle_type' => $this->vehicle_type ?? ($isCourier ? null : 'Motorcycle'),
            'plate_number' => $this->plate_number,
            'license_number' => $this->license_number,
            'license_expiry_date' => $this->license_expiry_date?->format('Y-m-d'),
            'vehicle_verification_status' => $this->vehicle_verification_status,
            'logistics_company_id' => $this->logistics_company_id ?? $this->sorting_center_id,
            'company_name' => $this->logisticsCompany?->full_name ?? $this->sortingCenter?->full_name ?? 'Logistics Partner',
            'sorting_center' => $this->sortingCenter?->full_name ?? $this->sortingCenter?->name,
            'assigned_hub_id' => $this->assigned_hub_id ?? $assignedZone?->hub_id,
            'assigned_hub' => $this->assignedHub?->name ?? $assignedZone?->hub?->name ?? $this->sortingCenter?->name ?? $this->sortingCenter?->full_name,
            'assigned_zone' => $assignedZone ? [
                'id' => $assignedZone->id,
                'name' => $assignedZone->name,
                'code' => $assignedZone->code,
                'hub_id' => $assignedZone->hub_id,
                'hub_name' => $assignedZone->hub?->name,
                'city_code' => $assignedZone->city_code,
                'city_name' => $assignedZone->city_name,
                'province_code' => $assignedZone->province_code,
                'province_name' => $assignedZone->province_name,
                'barangays' => $assignedZone->barangays->map(fn ($b) => [
                    'code' => $b->barangay_code,
                    'name' => $b->barangay_name,
                ])->values()->all(),
            ] : null,
            'earnings_balance' => $isCourier ? 0.0 : (float) ($this->earnings_balance ?? 0),
            'unremitted_cod_balance' => $isCourier ? 0.0 : (float) ($this->unremitted_cod_balance ?? 0),
            'active_jobs_count' => $isCourier ? 0 : (method_exists($this->resource, 'activeJobsCount') ? $this->activeJobsCount() : 0),
            'rating' => $avgRating,
            'reviews_count' => $reviewsCount,
            'total_deliveries' => $totalDeliveries,
        ];
    }
}

