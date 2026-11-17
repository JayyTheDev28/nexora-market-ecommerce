<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Zone;
use App\Services\OrderLogisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SortingController extends Controller
{
    /**
     * Display parcels awaiting sorting and sorted parcels grouped by destination area.
     */
    public function index(Request $request): View
    {
        $sortingCenterId = $request->user()->id;
        $tab = $request->query('tab', 'unsorted');
        $areaFilter = $request->query('area_id');
        $zoneFilter = $request->query('zone_id');
        $search = $request->query('q');

        // Modern Coverage Zones managed by this sorting hub
        $zones = Zone::where('logistics_company_id', $sortingCenterId)
            ->with(['hub', 'riders', 'barangays'])
            ->orderBy('name')
            ->get();

        foreach ($zones as $zone) {
            $bCodes = $zone->barangays->pluck('barangay_code')->all();
            $cityName = $zone->city_name;

            $zoneOrderQuery = fn ($statusGroup) => Order::where(fn ($q) => $q->where('sorting_center_id', $sortingCenterId)->orWhere('current_custody_logistics_id', $sortingCenterId))
                ->where(function ($q) use ($zone, $bCodes, $cityName) {
                    $q->where('delivery_zone_id', $zone->id)
                      ->orWhereHas('address', function ($aq) use ($bCodes, $cityName) {
                          if (! empty($bCodes)) {
                              $aq->whereIn('barangay_code', $bCodes);
                          } else {
                              $aq->where('municipality_name', $cityName);
                          }
                      });
                })
                ->where(function ($q) use ($statusGroup) {
                    if ($statusGroup === 'unsorted') {
                        $q->whereIn('status', ['at_sorting_center', 'received_at_hub']);
                    } else {
                        $q->where('status', 'sorted');
                    }
                });

            $zone->unsorted_orders_count = $zoneOrderQuery('unsorted')->count();
            $zone->sorted_orders_count = $zoneOrderQuery('sorted')->count();
        }

        // Delivery areas managed by this sorting hub (legacy fallback)
        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->with('courier')
            ->withCount([
                'orders as unsorted_orders_count' => fn ($q) => $q->whereIn('status', ['at_sorting_center', 'received_at_hub']),
                'orders as sorted_orders_count' => fn ($q) => $q->where('status', 'sorted'),
            ])
            ->orderBy('name')
            ->get();

        $eligibleForLocalSorting = function ($q) use ($sortingCenterId) {
            $q->where(function ($cq) use ($sortingCenterId) {
                $cq->where('current_custody_logistics_id', $sortingCenterId)
                    ->orWhere(function ($lq) use ($sortingCenterId) {
                        $lq->whereNull('current_custody_logistics_id')
                            ->where('sorting_center_id', $sortingCenterId);
                    });
            })->where(function ($dq) use ($sortingCenterId) {
                $dq->whereNull('delivery_logistics_id')
                    ->orWhere('delivery_logistics_id', $sortingCenterId);
            })->where(function ($hq) {
                $hq->whereNull('destination_hub_id')
                    ->orWhereColumn('current_hub_id', 'destination_hub_id');
            });
        };

        $query = Order::where($eligibleForLocalSorting)
            ->with([
                'buyer',
                'address',
                'deliveryZone.riders',
                'deliveryArea.courier',
                'items.product.seller.sellerProfile',
                'items.product.seller.address',
            ]);

        match ($tab) {
            'unsorted' => $query->whereIn('status', ['at_sorting_center', 'received_at_hub']),
            'sorted' => $query->where('status', 'sorted'),
            'all' => $query->whereIn('status', ['at_sorting_center', 'received_at_hub', 'sorted']),
            default => $query->whereIn('status', ['at_sorting_center', 'received_at_hub']),
        };

        if ($zoneFilter) {
            $activeZone = $zones->firstWhere('id', (int) $zoneFilter);
            if ($activeZone) {
                $bCodes = $activeZone->barangays->pluck('barangay_code')->all();
                $cityName = $activeZone->city_name;
                $query->where(function ($q) use ($activeZone, $bCodes, $cityName) {
                    $q->where('delivery_zone_id', $activeZone->id)
                      ->orWhereHas('address', function ($aq) use ($bCodes, $cityName) {
                          if (! empty($bCodes)) {
                              $aq->whereIn('barangay_code', $bCodes);
                          } else {
                              $aq->where('municipality_name', $cityName);
                          }
                      });
                });
            }
        } elseif ($areaFilter) {
            $query->where('delivery_area_id', $areaFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('address', fn ($aq) => $aq->where('municipality_name', 'like', "%{$search}%")->orWhere('barangay_name', 'like', "%{$search}%"));
            });
        }

        $parcels = $query->latest('updated_at')->latest('id')->paginate(15)->withQueryString();

        $baseCountQuery = fn () => Order::where($eligibleForLocalSorting);

        $counts = [
            'unsorted' => (clone $baseCountQuery())->whereIn('status', ['at_sorting_center', 'received_at_hub'])->count(),
            'sorted' => (clone $baseCountQuery())->where('status', 'sorted')->count(),
            'all' => (clone $baseCountQuery())->whereIn('status', ['at_sorting_center', 'received_at_hub', 'sorted'])->count(),
        ];

        return view('logistics.sorting.index', compact('parcels', 'counts', 'tab', 'deliveryAreas', 'zones', 'areaFilter', 'zoneFilter', 'search'));
    }

    /**
     * Confirm delivery coverage zone or area and advance parcel to sorted status.
     */
    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $companyId = (int) $request->user()->id;
        abort_unless(in_array($order->status, ['at_sorting_center', 'received_at_hub', 'sorted']), 422, 'Order must be at sorting center or hub to be sorted.');

        if ($order->current_custody_logistics_id !== null && (int) $order->current_custody_logistics_id !== $companyId) {
            return back()->withInput()->withErrors([
                'delivery_zone_id' => 'This parcel is not currently in your logistics company\'s custody.',
            ]);
        }

        if ($order->delivery_logistics_id !== null && (int) $order->delivery_logistics_id !== $companyId) {
            return back()->withInput()->withErrors([
                'delivery_zone_id' => 'This parcel is destined for another logistics company\'s service area and must be transferred via manifest instead of local delivery sorting.',
            ]);
        }

        // Guard: parcels requiring a hub transfer must reach their destination hub before local sorting
        if ($order->requiresHubTransfer() && !empty($order->destination_hub_id) && (int) $order->current_hub_id !== (int) $order->destination_hub_id) {
            return back()->withInput()->withErrors([
                'delivery_zone_id' => "This parcel is currently at " . ($order->currentHub?->name ?? 'origin hub') . " and must first be transferred to its destination hub (" . ($order->destinationHub?->name ?? 'destination hub') . ") before local sorting and delivery rider assignment.",
            ]);
        }

        $validated = $request->validate([
            'zone_id' => ['nullable', 'exists:zones,id'],
            'delivery_area_id' => ['nullable', 'exists:delivery_areas,id'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($validated['zone_id']) && empty($validated['delivery_area_id'])) {
            return back()->withInput()->withErrors([
                'zone_id' => 'Please select a destination coverage zone.',
            ]);
        }

        // 1. Modern Coverage Zone sorting
        if (! empty($validated['zone_id'])) {
            $zone = Zone::where('id', $validated['zone_id'])
                ->where('logistics_company_id', $request->user()->id)
                ->firstOrFail();

            $isOverride = $order->delivery_zone_id && $order->delivery_zone_id != $zone->id;
            if ($isOverride && empty($validated['override_reason'])) {
                return back()->withInput()->withErrors([
                    'override_reason' => 'A reason is required when manually overriding an existing assigned coverage zone.',
                ]);
            }

            $note = "Sorted to Coverage Zone: {$zone->name}.";
            if ($isOverride) {
                $note .= " Manual Override Reason: {$validated['override_reason']}";
            }

            OrderLogisticsService::transition(
                order: $order,
                newStatus: 'sorted',
                changedBy: $request->user()->id,
                note: $note,
                extraAttributes: [
                    'delivery_zone_id' => $zone->id,
                ]
            );

            return back()->with('status', "Parcel {$order->order_number} sorted successfully to '{$zone->name}'. Ready for rider assignment.");
        }

        // 2. Legacy Delivery Area sorting
        $area = DeliveryArea::where('id', $validated['delivery_area_id'])
            ->where('sorting_center_id', $request->user()->id)
            ->firstOrFail();

        $isOverride = $order->delivery_area_id && $order->delivery_area_id != $area->id;

        if ($isOverride && empty($validated['override_reason'])) {
            return back()->withInput()->withErrors([
                'override_reason' => 'A reason is required when manually overriding an existing assigned delivery area.',
            ]);
        }

        $note = "Sorted to Delivery Area: {$area->name}.";
        if ($isOverride) {
            $note .= " Manual Override Reason: {$validated['override_reason']}";
        }

        OrderLogisticsService::transition(
            order: $order,
            newStatus: 'sorted',
            changedBy: $request->user()->id,
            note: $note,
            extraAttributes: [
                'delivery_area_id' => $area->id,
            ]
        );

        return back()->with('status', "Parcel {$order->order_number} sorted successfully to '{$area->name}'. Ready for rider assignment.");
    }

    /**
     * Batch sort parcels that have an auto-matched delivery area.
     */
    public function batchSort(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::whereIn('id', $validated['order_ids'])
            ->where('sorting_center_id', $request->user()->id)
            ->whereIn('status', ['at_sorting_center', 'received_at_hub'])
            ->whereNotNull('delivery_area_id')
            ->with('deliveryArea')
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('info', 'No eligible unsorted parcels with resolved delivery areas were selected.');
        }

        $count = 0;
        foreach ($orders as $order) {
            OrderLogisticsService::transition(
                order: $order,
                newStatus: 'sorted',
                changedBy: $request->user()->id,
                note: "Batch sorted to Destination Area: {$order->deliveryArea->name}."
            );
            $count++;
        }

        return back()->with('status', "Successfully sorted {$count} parcel(s) to their destination delivery areas.");
    }
}
