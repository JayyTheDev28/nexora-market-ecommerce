<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLogisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    /**
     * Display parcels awaiting rider assignment and currently assigned deliveries.
     */
    public function index(Request $request): View
    {
        $sortingCenterId = $request->user()->id;
        $tab = $request->query('tab', 'unassigned');
        $areaFilter = $request->query('area_id');
        $search = $request->query('q');

        $query = Order::where(fn ($q) => $q->where('sorting_center_id', $sortingCenterId)->orWhere('current_custody_logistics_id', $sortingCenterId))
            ->with([
                'buyer',
                'address',
                'deliveryArea',
                'deliveryCourier.courierProfile',
                'items.product',
                'originHub',
                'destinationHub',
                'currentHub',
            ]);

        match ($tab) {
            'unassigned' => $query->whereIn('status', ['sorted', 'received_at_hub', 'at_sorting_center']),
            'assigned' => $query->where('status', 'assigned_to_rider'),
            'all' => $query->whereIn('status', ['sorted', 'received_at_hub', 'at_sorting_center', 'assigned_to_rider', 'out_for_delivery']),
            default => $query->whereIn('status', ['sorted', 'received_at_hub', 'at_sorting_center']),
        };

        if ($areaFilter) {
            $query->where('delivery_area_id', $areaFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('deliveryCourier', fn ($cq) => $cq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $parcels = $query->latest()->paginate(15)->withQueryString();

        $baseCountQuery = fn () => Order::where(fn ($q) => $q->where('sorting_center_id', $sortingCenterId)->orWhere('current_custody_logistics_id', $sortingCenterId));

        $counts = [
            'unassigned' => (clone $baseCountQuery())->whereIn('status', ['sorted', 'received_at_hub', 'at_sorting_center'])->count(),
            'assigned' => (clone $baseCountQuery())->where('status', 'assigned_to_rider')->count(),
            'all' => (clone $baseCountQuery())->whereIn('status', ['sorted', 'received_at_hub', 'at_sorting_center', 'assigned_to_rider', 'out_for_delivery'])->count(),
        ];

        // Active and approved riders with their workload and assigned areas/zones
        $couriers = User::whereIn('role', ['rider', 'courier'])
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('courierProfile', fn ($cp) => $cp->where('sorting_center_id', $sortingCenterId)->orWhere('logistics_company_id', $sortingCenterId))
            ->with(['courierProfile', 'assignedZones.barangays', 'deliveryAreas' => fn ($q) => $q->where('sorting_center_id', $sortingCenterId)])
            ->withCount([
                'activeDeliveries as workload_count',
            ])
            ->orderBy('first_name')
            ->get();

        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->with('courier')
            ->get();

        $zones = \App\Models\Zone::where('logistics_company_id', $sortingCenterId)->with('barangays')->get();
        $hubs = \App\Models\Hub::where('logistics_company_id', $sortingCenterId)->get();

        return view('logistics.assignments.index', compact('parcels', 'counts', 'tab', 'couriers', 'deliveryAreas', 'zones', 'hubs', 'areaFilter', 'search'));
    }

    /**
     * Assign or reassign a parcel to an eligible rider.
     */
    public function assign(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'courier_id' => ['required', 'exists:users,id'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sortingCenterId = $request->user()->id;

        // Concurrency-safe atomic assignment
        return DB::transaction(function () use ($request, $order, $validated, $sortingCenterId) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array($lockedOrder->status, ['sorted', 'assigned_to_rider', 'received_at_hub', 'at_sorting_center']),
                422,
                "Order cannot be assigned in status '" . ucwords(str_replace('_', ' ', $lockedOrder->status)) . "'."
            );

            // Guard: If parcel requires a hub transfer and has not reached the destination hub yet
            if ($lockedOrder->requiresHubTransfer() && (int) $lockedOrder->current_hub_id !== (int) $lockedOrder->destination_hub_id) {
                return back()->withInput()->withErrors([
                    'courier_id' => "This parcel requires a hub transfer to {$lockedOrder->destinationHub?->name} before local delivery assignment. Current status: " . ucwords(str_replace('_', ' ', $lockedOrder->status)) . ".",
                ]);
            }

            $courier = User::where('id', $validated['courier_id'])
                ->whereIn('role', ['rider', 'courier'])
                ->lockForUpdate()
                ->firstOrFail();

            // Validate courier account eligibility
            abort_unless($courier->isApproved(), 422, 'Cannot assign parcel to an unapproved courier.');
            abort_unless($courier->isActive(), 422, 'Cannot assign parcel to a deactivated courier.');

            // Verify area or zone coverage eligibility
            $barangayCode = $lockedOrder->address?->barangay_code ?? ($lockedOrder->address_snapshot['barangay_code'] ?? null);
            $barangayName = $lockedOrder->address?->barangay_name ?? ($lockedOrder->address_snapshot['barangay_name'] ?? null);

            $coversZone = $courier->assignedZones()
                ->whereHas('barangays', function ($b) use ($barangayCode, $barangayName) {
                    if ($barangayCode) {
                        $b->where('barangay_code', $barangayCode);
                    } elseif ($barangayName) {
                        $b->where('barangay_name', 'like', $barangayName);
                    }
                })
                ->exists();
            $coversArea = $coversZone || $courier->deliveryAreas()->where('delivery_areas.id', $lockedOrder->delivery_area_id)->exists();

            if (! $coversArea && empty($validated['override_reason'])) {
                return back()->withInput()->withErrors([
                    'override_reason' => "Rider {$courier->full_name} is not assigned to this parcel's zone/delivery area. An override reason is required.",
                ]);
            }

            $isReassignment = $lockedOrder->delivery_courier_id && $lockedOrder->delivery_courier_id != $courier->id;

            $note = "Assigned for delivery to rider: {$courier->full_name} ({$courier->contact_number}).";
            if (! $coversArea) {
                $note .= " [Coverage Override: {$validated['override_reason']}]";
            }
            if ($isReassignment) {
                $note .= " (Reassigned from previous courier)";
            }
            if (! empty($validated['notes'])) {
                $note .= " Instructions: {$validated['notes']}";
            }

            OrderLogisticsService::transition(
                order: $lockedOrder,
                newStatus: 'assigned_to_rider',
                changedBy: $request->user()->id,
                note: $note,
                extraAttributes: [
                    'delivery_courier_id' => $courier->id,
                    'delivery_rider_id' => $courier->id,
                    'sorting_center_id' => $sortingCenterId,
                    'delivery_logistics_id' => $sortingCenterId,
                ]
            );

            return back()->with('status', "Parcel {$lockedOrder->order_number} successfully assigned to rider {$courier->full_name}.");
        });
    }

    /**
     * Batch assign all sorted parcels for a delivery area to the area's designated rider.
     */
    public function batchAreaAssign(Request $request, DeliveryArea $area): RedirectResponse
    {
        abort_unless($area->sorting_center_id === $request->user()->id, 403);
        abort_unless($area->courier_id, 422, "Delivery area '{$area->name}' has no assigned primary courier.");

        $courier = $area->courier;
        abort_unless($courier->isApproved() && $courier->isActive(), 422, "Assigned area courier {$courier->full_name} is inactive or unapproved.");

        $orders = Order::where('sorting_center_id', $request->user()->id)
            ->where('delivery_area_id', $area->id)
            ->where('status', 'sorted')
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('info', "No unassigned sorted parcels found for area '{$area->name}'.");
        }

        $count = 0;
        foreach ($orders as $order) {
            OrderLogisticsService::transition(
                order: $order,
                newStatus: 'assigned_to_rider',
                changedBy: $request->user()->id,
                note: "Batch area assigned to primary courier: {$courier->full_name}.",
                extraAttributes: [
                    'delivery_courier_id' => $courier->id,
                ]
            );
            $count++;
        }

        return back()->with('status', "Successfully assigned {$count} sorted parcel(s) in '{$area->name}' to rider {$courier->full_name}.");
    }
}
