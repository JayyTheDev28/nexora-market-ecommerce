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

        $query = Order::where('sorting_center_id', $sortingCenterId)
            ->with([
                'buyer',
                'address',
                'deliveryArea',
                'deliveryCourier.courierProfile',
                'items.product',
            ]);

        match ($tab) {
            'unassigned' => $query->where('status', 'sorted'),
            'assigned' => $query->where('status', 'assigned_to_rider'),
            'all' => $query->whereIn('status', ['sorted', 'assigned_to_rider', 'out_for_delivery']),
            default => $query->where('status', 'sorted'),
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

        $counts = [
            'unassigned' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'sorted')->count(),
            'assigned' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'assigned_to_rider')->count(),
            'all' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['sorted', 'assigned_to_rider', 'out_for_delivery'])->count(),
        ];

        // Active and approved couriers with their workload and assigned areas
        $couriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->with(['courierProfile', 'deliveryAreas' => fn ($q) => $q->where('sorting_center_id', $sortingCenterId)])
            ->withCount([
                'activeDeliveries as workload_count',
            ])
            ->orderBy('first_name')
            ->get();

        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->with('courier')
            ->get();

        return view('logistics.assignments.index', compact('parcels', 'counts', 'tab', 'couriers', 'deliveryAreas', 'areaFilter', 'search'));
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
                in_array($lockedOrder->status, ['sorted', 'assigned_to_rider']),
                422,
                "Order cannot be assigned in status '" . ucwords(str_replace('_', ' ', $lockedOrder->status)) . "'."
            );

            $courier = User::where('id', $validated['courier_id'])
                ->where('role', 'courier')
                ->lockForUpdate()
                ->firstOrFail();

            // Validate courier account eligibility
            abort_unless($courier->isApproved(), 422, 'Cannot assign parcel to an unapproved courier.');
            abort_unless($courier->isActive(), 422, 'Cannot assign parcel to a deactivated courier.');

            // Verify area eligibility
            $coversArea = $courier->deliveryAreas()->where('delivery_areas.id', $lockedOrder->delivery_area_id)->exists();

            if (! $coversArea && empty($validated['override_reason'])) {
                return back()->withInput()->withErrors([
                    'override_reason' => "Courier {$courier->full_name} is not assigned to this parcel's delivery area. An override reason is required.",
                ]);
            }

            $isReassignment = $lockedOrder->delivery_courier_id && $lockedOrder->delivery_courier_id != $courier->id;

            $note = "Assigned for delivery to rider: {$courier->full_name} ({$courier->contact_number}).";
            if (! $coversArea) {
                $note .= " [Area Override: {$validated['override_reason']}]";
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
                    'sorting_center_id' => $sortingCenterId,
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
