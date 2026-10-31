<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Order;
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
        $search = $request->query('q');

        // Delivery areas managed by this sorting hub
        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->with('courier')
            ->withCount([
                'orders as unsorted_orders_count' => fn ($q) => $q->where('status', 'at_sorting_center'),
                'orders as sorted_orders_count' => fn ($q) => $q->where('status', 'sorted'),
            ])
            ->orderBy('name')
            ->get();

        $query = Order::where('sorting_center_id', $sortingCenterId)
            ->with([
                'buyer',
                'address',
                'deliveryArea.courier',
                'items.product.seller.sellerProfile',
                'items.product.seller.address',
            ]);

        match ($tab) {
            'unsorted' => $query->where('status', 'at_sorting_center'),
            'sorted' => $query->where('status', 'sorted'),
            'all' => $query->whereIn('status', ['at_sorting_center', 'sorted']),
            default => $query->where('status', 'at_sorting_center'),
        };

        if ($areaFilter) {
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

        $parcels = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'unsorted' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'at_sorting_center')->count(),
            'sorted' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'sorted')->count(),
            'all' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['at_sorting_center', 'sorted'])->count(),
        ];

        return view('logistics.sorting.index', compact('parcels', 'counts', 'tab', 'deliveryAreas', 'areaFilter', 'search'));
    }

    /**
     * Confirm delivery area and advance parcel to sorted status.
     */
    public function confirm(Request $request, Order $order): RedirectResponse
    {
        abort_unless(in_array($order->status, ['at_sorting_center', 'sorted']), 422, 'Order must be at sorting center to be sorted.');

        $validated = $request->validate([
            'delivery_area_id' => ['required', 'exists:delivery_areas,id'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

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
            ->where('status', 'at_sorting_center')
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
