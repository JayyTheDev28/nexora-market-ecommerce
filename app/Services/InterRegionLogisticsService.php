<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InterRegionLogisticsService
{
    /**
     * Match origin and destination logistics companies, hubs, and zones for an order.
     */
    public function matchLogisticsAndRouteOrder(Order $order): Order
    {
        $sellerAddress = $order->seller_address;
        $buyerAddress = $order->address;

        $sellerBarangayCode = $sellerAddress?->barangay_code;
        $sellerCityCode = $sellerAddress?->municipality_code;
        $sellerProvinceCode = $sellerAddress?->province_code;

        $buyerBarangayCode = $buyerAddress?->barangay_code;
        $buyerCityCode = $buyerAddress?->municipality_code;
        $buyerProvinceCode = $buyerAddress?->province_code;

        // 1. Resolve Pickup Logistics, Zone, and Origin Hub
        $pickupRoute = $this->resolveCoverage(
            $sellerBarangayCode,
            $sellerCityCode,
            $sellerProvinceCode
        );

        // 2. Resolve Delivery Logistics, Zone, and Destination Hub
        $deliveryRoute = $this->resolveCoverage(
            $buyerBarangayCode,
            $buyerCityCode,
            $buyerProvinceCode
        );

        // Fallbacks if no specific zone covers the area
        $defaultLogistics = User::whereIn('role', ['logistics', 'sorting_center'])
            ->where('approval_status', 'approved')
            ->first();

        $pickupLogisticsId = $pickupRoute['logistics_company_id'] ?? $defaultLogistics?->id;
        $deliveryLogisticsId = $deliveryRoute['logistics_company_id'] ?? $defaultLogistics?->id;

        $pickupZoneId = $pickupRoute['zone_id'] ?? null;
        $deliveryZoneId = $deliveryRoute['zone_id'] ?? null;

        $originHubId = $pickupRoute['hub_id'] ?? Hub::where('logistics_company_id', $pickupLogisticsId)->value('id');
        $destinationHubId = $deliveryRoute['hub_id'] ?? Hub::where('logistics_company_id', $deliveryLogisticsId)->value('id');

        $order->update([
            'pickup_logistics_id' => $pickupLogisticsId,
            'delivery_logistics_id' => $deliveryLogisticsId,
            'current_custody_logistics_id' => $pickupLogisticsId,
            'pickup_zone_id' => $pickupZoneId,
            'delivery_zone_id' => $deliveryZoneId,
            'origin_hub_id' => $originHubId,
            'destination_hub_id' => $destinationHubId,
            'sorting_center_id' => $pickupLogisticsId, // for backwards compatibility
        ]);

        return $order->fresh();
    }

    /**
     * Resolve zone, hub, and company covering a given location using:
     * 1. Exact barangay code match
     * 2. City fallback match
     * 3. Province fallback match
     */
    public function resolveCoverage(?string $barangayCode, ?string $cityCode, ?string $provinceCode): ?array
    {
        // 1. Exact barangay match
        if ($barangayCode) {
            $zb = ZoneBarangay::where('barangay_code', $barangayCode)->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'barangay',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        // 2. City fallback match
        if ($cityCode) {
            $zb = ZoneBarangay::where('city_code', $cityCode)->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'city',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        // 3. Province fallback match
        if ($provinceCode) {
            $zb = ZoneBarangay::where('province_code', $provinceCode)->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'province',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        return null;
    }

    /**
     * Find best eligible rider for a company covering an area.
     * Hierarchy: Exact barangay -> City fallback -> Province fallback -> General company rider.
     * Tie-breaking: Fewest active jobs (< 3).
     * Strictly company-isolated (never offers outside company).
     */
    public function findBestEligibleRider(
        int $logisticsCompanyId,
        ?string $barangayCode = null,
        ?string $cityCode = null,
        ?string $provinceCode = null,
        array $excludeRiderIds = []
    ): ?User {
        // Base candidate pool: riders strictly belonging to this company, online, approved, active
        $baseQuery = fn () => User::query()
            ->whereIn('role', ['rider', 'courier'])
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereNotIn('id', $excludeRiderIds)
            ->whereHas('courierProfile', function ($q) use ($logisticsCompanyId) {
                $q->where('is_online', true)
                  ->where(function ($cq) use ($logisticsCompanyId) {
                      $cq->where('logistics_company_id', $logisticsCompanyId)
                         ->orWhere('sorting_center_id', $logisticsCompanyId);
                  });
            })
            ->with(['courierProfile', 'assignedZones.barangays']);

        $filterAndSort = function ($candidates) {
            return $candidates
                ->filter(fn (User $rider) => $rider->courierProfile && $rider->courierProfile->activeJobsCount() < 3)
                ->sortBy(fn (User $rider) => $rider->courierProfile->activeJobsCount())
                ->first();
        };

        // Priority 1: Exact Barangay Match
        if ($barangayCode) {
            $candidates = (clone $baseQuery())
                ->whereHas('assignedZones.barangays', fn ($q) => $q->where('barangay_code', $barangayCode))
                ->get();
            $best = $filterAndSort($candidates);
            if ($best) {
                return $best;
            }
        }

        // Priority 2: City Fallback Match
        if ($cityCode) {
            $candidates = (clone $baseQuery())
                ->whereHas('assignedZones.barangays', fn ($q) => $q->where('city_code', $cityCode))
                ->get();
            $best = $filterAndSort($candidates);
            if ($best) {
                return $best;
            }
        }

        // Priority 3: Province Fallback Match
        if ($provinceCode) {
            $candidates = (clone $baseQuery())
                ->whereHas('assignedZones.barangays', fn ($q) => $q->where('province_code', $provinceCode))
                ->get();
            $best = $filterAndSort($candidates);
            if ($best) {
                return $best;
            }
        }

        // Priority 4: Company Rider General Fallback
        $candidates = (clone $baseQuery())->get();
        return $filterAndSort($candidates);
    }

    /**
     * Confirm receipt of parcel at origin or destination hub.
     */
    public function confirmReceiptAtHub(Order $order, Hub $hub, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $hub, $actor, $note) {
            $isSameCompany = (int) $order->pickup_logistics_id === (int) $order->delivery_logistics_id;

            $newStatus = 'received_at_hub';
            $defaultNote = "Parcel received at Hub: {$hub->name} ({$hub->city_name}, {$hub->province_name}).";

            if ($isSameCompany) {
                // Same-company delivery: skip transfer and go directly to sorting/delivery preparation
                $newStatus = 'sorted';
                $defaultNote .= " Same-company logistics route: Parcel sorted directly for local delivery.";
            }

            $order->update([
                'status' => $newStatus,
                'current_custody_logistics_id' => $hub->logistics_company_id,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'changed_by' => $actor->id,
                'note' => $note ?: $defaultNote,
                'changed_at' => now(),
            ]);

            return $order->fresh();
        });
    }

    /**
     * Dispatch an inter-hub transfer to another logistics company.
     * Custody STAYS with the sender company during transit!
     */
    public function dispatchHubTransfer(
        Order $order,
        Hub $senderHub,
        Hub $receiverHub,
        User $actor,
        ?string $notes = null
    ): HubTransfer {
        return DB::transaction(function () use ($order, $senderHub, $receiverHub, $actor, $notes) {
            // Validate sender custody
            abort_unless(
                (int) $order->current_custody_logistics_id === (int) $senderHub->logistics_company_id,
                403,
                'Parcel is not in the custody of the sending logistics company.'
            );

            $transfer = HubTransfer::create([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'sender_logistics_id' => $senderHub->logistics_company_id,
                'sender_hub_id' => $senderHub->id,
                'receiver_logistics_id' => $receiverHub->logistics_company_id,
                'receiver_hub_id' => $receiverHub->id,
                'status' => 'in_transit',
                'dispatched_at' => now(),
                'dispatched_by' => $actor->id,
                'notes' => $notes,
            ]);

            // Status transitions to in_transit_hub, custody remains with sender
            $order->update([
                'status' => 'in_transit_hub',
                'current_custody_logistics_id' => $senderHub->logistics_company_id,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'in_transit_hub',
                'changed_by' => $actor->id,
                'note' => "Dispatched in transit from {$senderHub->name} to {$receiverHub->name}. Custody remains with {$senderHub->logisticsCompany?->full_name}.",
                'changed_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Confirm receipt of an inter-hub transfer at the destination hub.
     * CUSTODY MOVES TO RECEIVER ONLY HERE!
     */
    public function confirmHubTransferReceipt(
        HubTransfer $transfer,
        User $actor,
        ?string $notes = null
    ): Order {
        return DB::transaction(function () use ($transfer, $actor, $notes) {
            abort_unless(
                $transfer->status === 'in_transit',
                422,
                'Transfer is not currently in transit.'
            );

            // Verify receiver belongs to destination company
            $receiverLogisticsId = $transfer->receiver_logistics_id;
            abort_unless(
                (int) $actor->id === (int) $receiverLogisticsId,
                403,
                'Only authorized staff of the receiving logistics company can confirm parcel receipt.'
            );

            $transfer->update([
                'status' => 'received',
                'received_at' => now(),
                'received_by' => $actor->id,
                'notes' => $notes ? trim("{$transfer->notes} | Receipt note: {$notes}") : $transfer->notes,
            ]);

            $order = $transfer->order;

            // Custody transfers to receiving company!
            $order->update([
                'status' => 'received_at_hub',
                'current_custody_logistics_id' => $receiverLogisticsId,
                'sorting_center_id' => $receiverLogisticsId, // so receiver's dashboard has it
                'destination_hub_id' => $transfer->receiver_hub_id,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'received_at_hub',
                'changed_by' => $actor->id,
                'note' => "Transfer received at destination hub: {$transfer->receiverHub?->name}. Custody officially transferred to {$transfer->receiverLogistics?->full_name}.",
                'changed_at' => now(),
            ]);

            return $order->fresh();
        });
    }
}

