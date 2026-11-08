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
        $sellerAddress = $order->seller_address ?? Address::where('user_id', $order->seller_id)->first();
        $buyerAddress = $order->address;
        $buyerSnapshot = $order->address_snapshot;

        $sellerBarangayCode = $sellerAddress?->barangay_code;
        $sellerCityCode = $sellerAddress?->municipality_code;
        $sellerProvinceCode = $sellerAddress?->province_code;
        $sellerBarangayName = $sellerAddress?->barangay_name;
        $sellerCityName = $sellerAddress?->municipality_name;
        $sellerProvinceName = $sellerAddress?->province_name;

        $buyerBarangayCode = $buyerAddress?->barangay_code ?? ($buyerSnapshot['barangay_code'] ?? null);
        $buyerCityCode = $buyerAddress?->municipality_code ?? ($buyerSnapshot['municipality_code'] ?? null);
        $buyerProvinceCode = $buyerAddress?->province_code ?? ($buyerSnapshot['province_code'] ?? null);
        $buyerBarangayName = $buyerAddress?->barangay_name ?? ($buyerSnapshot['barangay_name'] ?? null);
        $buyerCityName = $buyerAddress?->municipality_name ?? ($buyerSnapshot['municipality_name'] ?? null);
        $buyerProvinceName = $buyerAddress?->province_name ?? ($buyerSnapshot['province_name'] ?? null);

        // 1. Resolve Pickup Logistics, Zone, and Origin Hub
        $pickupRoute = $this->resolveCoverage(
            $sellerBarangayCode,
            $sellerCityCode,
            $sellerProvinceCode,
            $sellerBarangayName,
            $sellerCityName,
            $sellerProvinceName
        );

        // 2. Resolve Delivery Logistics, Zone, and Destination Hub
        $deliveryRoute = $this->resolveCoverage(
            $buyerBarangayCode,
            $buyerCityCode,
            $buyerProvinceCode,
            $buyerBarangayName,
            $buyerCityName,
            $buyerProvinceName
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

        $updateData = [
            'pickup_logistics_id' => $pickupLogisticsId,
            'delivery_logistics_id' => $deliveryLogisticsId,
            'pickup_zone_id' => $pickupZoneId,
            'delivery_zone_id' => $deliveryZoneId,
            'origin_hub_id' => $originHubId,
            'destination_hub_id' => $destinationHubId,
            'sorting_center_id' => $pickupLogisticsId, // for backwards compatibility
        ];

        if (empty($order->current_custody_logistics_id)) {
            $updateData['current_custody_logistics_id'] = $pickupLogisticsId;
        }

        $order->update($updateData);

        return $order->fresh();
    }

    /**
     * Resolve zone, hub, and company covering a given location using:
     * 1. Exact barangay code match
     * 2. Barangay name + city name match
     * 3. City code fallback match
     * 4. City name fallback match
     * 5. Province code fallback match
     * 6. Direct Hub location match
     */
    public function resolveCoverage(
        ?string $barangayCode,
        ?string $cityCode,
        ?string $provinceCode,
        ?string $barangayName = null,
        ?string $cityName = null,
        ?string $provinceName = null
    ): ?array {
        // 1. Exact barangay code match
        if ($barangayCode) {
            $zb = ZoneBarangay::where('barangay_code', $barangayCode)->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'barangay_code',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        // 2. Barangay name + City name match
        if ($barangayName && $cityName) {
            $zb = ZoneBarangay::where('barangay_name', 'like', $barangayName)
                ->where('city_name', 'like', "%{$cityName}%")
                ->with('zone')
                ->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'barangay_name',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        // 3. City code fallback match
        if ($cityCode) {
            $zb = ZoneBarangay::where('city_code', $cityCode)->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'city_code',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        // 4. City name fallback match on ZoneBarangay or Zone
        if ($cityName) {
            $zb = ZoneBarangay::where('city_name', 'like', "%{$cityName}%")->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'city_name_zone_barangay',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }

            $zone = Zone::where('city_name', 'like', "%{$cityName}%")->first();
            if ($zone) {
                return [
                    'match_level' => 'city_name_zone',
                    'zone_id' => $zone->id,
                    'logistics_company_id' => $zone->logistics_company_id,
                    'hub_id' => $zone->hub_id,
                ];
            }

            // Direct Hub match by city
            $hub = Hub::where('is_active', true)
                ->where(function ($q) use ($cityName) {
                    $q->where('city_name', 'like', "%{$cityName}%")
                      ->orWhere('name', 'like', "%{$cityName}%");
                })
                ->first();
            if ($hub) {
                $hubZone = Zone::where('hub_id', $hub->id)->first();
                return [
                    'match_level' => 'hub_city',
                    'zone_id' => $hubZone?->id,
                    'logistics_company_id' => $hub->logistics_company_id,
                    'hub_id' => $hub->id,
                ];
            }
        }

        // 5. Province code fallback match
        if ($provinceCode) {
            $zb = ZoneBarangay::where('province_code', $provinceCode)->with('zone')->first();
            if ($zb && $zb->zone) {
                return [
                    'match_level' => 'province_code',
                    'zone_id' => $zb->zone_id,
                    'logistics_company_id' => $zb->logistics_company_id,
                    'hub_id' => $zb->zone->hub_id,
                ];
            }
        }

        // 6. Province name fallback match
        if ($provinceName) {
            $hub = Hub::where('is_active', true)
                ->where('province_name', 'like', "%{$provinceName}%")
                ->first();
            if ($hub) {
                $hubZone = Zone::where('hub_id', $hub->id)->first();
                return [
                    'match_level' => 'hub_province',
                    'zone_id' => $hubZone?->id,
                    'logistics_company_id' => $hub->logistics_company_id,
                    'hub_id' => $hub->id,
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
            // Ensure order has routing populated
            if (empty($order->origin_hub_id) || empty($order->destination_hub_id)) {
                $order = $this->matchLogisticsAndRouteOrder($order);
            }

            // Determine if origin and destination use the same hub
            $isSameHub = !empty($order->origin_hub_id) && !empty($order->destination_hub_id) && (int) $order->origin_hub_id === (int) $order->destination_hub_id;
            $isDestinationHubReached = (int) $hub->id === (int) $order->destination_hub_id;

            $newStatus = 'received_at_hub';
            $defaultNote = "Parcel received at Hub: {$hub->name} ({$hub->city_name}, {$hub->province_name}).";

            if ($isSameHub || $isDestinationHubReached) {
                // Same hub delivery or already reached destination hub:
                // Skip unnecessary hub-to-hub transportation and proceed to delivery assignment
                $newStatus = 'sorted';
                $defaultNote .= " Destination hub confirmed: Ready for local delivery rider assignment.";
            } else {
                // Different hubs: parcel is received at origin hub, awaiting hub dispatch
                $isInternal = (int) $order->pickup_logistics_id === (int) $order->delivery_logistics_id;
                $destHub = $order->destinationHub;
                $transferType = $isInternal ? 'Internal Hub Transfer' : 'Inter-Company Custody Transfer';
                $defaultNote .= " Staged for {$transferType} to " . ($destHub ? $destHub->name : 'destination hub') . ".";
            }

            $order->update([
                'status' => $newStatus,
                'current_hub_id' => $hub->id,
                'origin_hub_id' => $order->origin_hub_id ?: $hub->id,
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
     * Dispatch an internal or inter-company hub transfer to the destination hub.
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

            abort_if(
                (int) $senderHub->id === (int) $receiverHub->id,
                422,
                'Origin and destination hubs cannot be the same.'
            );

            $isInternal = (int) $senderHub->logistics_company_id === (int) $receiverHub->logistics_company_id;

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

            // Status transitions to in_transit_hub, custody remains with sender, current_hub_id is null in transit
            $order->update([
                'status' => 'in_transit_hub',
                'current_hub_id' => null,
                'current_custody_logistics_id' => $senderHub->logistics_company_id,
                'destination_hub_id' => $receiverHub->id,
            ]);

            $dispatchMsg = $isInternal
                ? "Internal Hub Transfer: Dispatched in transit between hubs from {$senderHub->name} to {$receiverHub->name}."
                : "Inter-Company Custody Transfer: Dispatched in transit from {$senderHub->name} to {$receiverHub->name}. Custody remains with {$senderHub->logisticsCompany?->full_name} during transit.";

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'in_transit_hub',
                'changed_by' => $actor->id,
                'note' => $dispatchMsg,
                'changed_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Confirm receipt of a hub transfer at the destination hub.
     * CUSTODY MOVES TO RECEIVER ONLY HERE!
     * Supports both internal transfers and inter-company transfers.
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

            $isInternal = (int) $transfer->sender_logistics_id === (int) $transfer->receiver_logistics_id;
            $receiverLogisticsId = $transfer->receiver_logistics_id;

            // Verify receiver belongs to destination company
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

            $orderUpdates = [
                'status' => 'received_at_hub',
                'current_hub_id' => $transfer->receiver_hub_id,
                'current_custody_logistics_id' => $receiverLogisticsId,
                'sorting_center_id' => $receiverLogisticsId, // so receiver's dashboard has it
                'destination_hub_id' => $transfer->receiver_hub_id,
            ];

            // Auto-match destination delivery area if available
            $matchedArea = \App\Models\DeliveryArea::where('sorting_center_id', $receiverLogisticsId)
                ->where(function ($q) use ($order) {
                    if ($order->address?->municipality_name) {
                        $q->where('name', 'like', "%{$order->address->municipality_name}%");
                    }
                })
                ->first();

            if ($matchedArea && empty($order->delivery_area_id)) {
                $orderUpdates['delivery_area_id'] = $matchedArea->id;
            }

            // Custody transfers to receiving company! Parcel appears in destination hub inventory
            $order->update($orderUpdates);

            $receiptMsg = $isInternal
                ? "Internal hub transfer confirmed and received at destination hub: {$transfer->receiverHub?->name}. Parcel is now in destination hub inventory, ready for local delivery rider assignment."
                : "Transfer received and confirmed at destination hub: {$transfer->receiverHub?->name}. Custody officially transferred to {$transfer->receiverLogistics?->full_name}. Ready for local delivery rider assignment.";

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'received_at_hub',
                'changed_by' => $actor->id,
                'note' => $receiptMsg,
                'changed_at' => now(),
            ]);

            return $order->fresh();
        });
    }
}

