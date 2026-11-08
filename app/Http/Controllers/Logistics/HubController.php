<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\Order;
use App\Models\PsgcProvince;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubController extends Controller
{
    /**
     * Display a listing of hubs owned by this logistics company.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->id;

        $hubs = Hub::where('logistics_company_id', $companyId)
            ->withCount([
                'zones',
                'transfersSent as in_transit_sent_count' => fn ($q) => $q->where('status', 'in_transit'),
                'transfersReceived as in_transit_incoming_count' => fn ($q) => $q->where('status', 'in_transit'),
            ])
            ->latest()
            ->paginate(15);

        // Calculate parcels currently at hub
        $hubs->getCollection()->transform(function ($hub) use ($companyId) {
            $hub->parcels_at_hub_count = Order::where(function ($q) use ($hub) {
                    $q->where('current_hub_id', $hub->id)
                      ->orWhere(function ($q2) use ($hub) {
                          $q2->whereNull('current_hub_id')
                             ->where('origin_hub_id', $hub->id)
                             ->whereDoesntHave('transfers', fn ($t) => $t->where('status', 'in_transit'));
                      });
                })
                ->where('current_custody_logistics_id', $companyId)
                ->whereIn('status', ['received_at_hub', 'at_sorting_center', 'sorted'])
                ->count();
            return $hub;
        });

        return view('logistics.hubs.index', compact('hubs'));
    }

    /**
     * Display hub details and parcel inventory currently at this hub.
     */
    public function show(Request $request, Hub $hub): View
    {
        abort_unless((int) $hub->logistics_company_id === (int) $request->user()->id, 403);

        $tab = $request->query('tab', 'all');
        $search = $request->query('q');

        $query = Order::where(function ($q) use ($hub) {
                $q->where('current_hub_id', $hub->id)
                  ->orWhere(function ($q2) use ($hub) {
                      $q2->whereNull('current_hub_id')
                         ->where('origin_hub_id', $hub->id)
                         ->whereDoesntHave('transfers', fn ($t) => $t->where('status', 'in_transit'));
                  });
            })
            ->whereIn('status', ['received_at_hub', 'at_sorting_center', 'sorted', 'assigned_to_rider'])
            ->with(['buyer', 'address', 'originHub', 'destinationHub.logisticsCompany', 'deliveryLogistics', 'items.product', 'deliveryCourier']);

        if ($tab === 'staged_transfer') {
            $query->whereColumn('origin_hub_id', '!=', 'destination_hub_id')
                  ->where('destination_hub_id', '!=', $hub->id);
        } elseif ($tab === 'local_delivery') {
            $query->where(function ($q) use ($hub) {
                $q->where('destination_hub_id', $hub->id)
                  ->orWhereColumn('origin_hub_id', '=', 'destination_hub_id');
            });
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

        $allAtHub = Order::where(function ($q) use ($hub) {
                $q->where('current_hub_id', $hub->id)
                  ->orWhere(function ($q2) use ($hub) {
                      $q2->whereNull('current_hub_id')
                         ->where('origin_hub_id', $hub->id)
                         ->whereDoesntHave('transfers', fn ($t) => $t->where('status', 'in_transit'));
                  });
            })
            ->whereIn('status', ['received_at_hub', 'at_sorting_center', 'sorted', 'assigned_to_rider']);

        $counts = [
            'all' => (clone $allAtHub)->count(),
            'staged_transfer' => (clone $allAtHub)->whereColumn('origin_hub_id', '!=', 'destination_hub_id')->where('destination_hub_id', '!=', $hub->id)->count(),
            'local_delivery' => (clone $allAtHub)->where(fn ($q) => $q->where('destination_hub_id', $hub->id)->orWhereColumn('origin_hub_id', '=', 'destination_hub_id'))->count(),
        ];

        return view('logistics.hubs.show', compact('hub', 'parcels', 'counts', 'tab', 'search'));
    }

    /**
     * Show the form for creating a new hub.
     */
    public function create(): View
    {
        $provinces = PsgcProvince::orderBy('name')->get();
        return view('logistics.hubs.create', compact('provinces'));
    }

    /**
     * Store a newly created hub.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'unique:hubs,code'],
            'province_code' => ['required', 'string', 'max:20'],
            'province_name' => ['required', 'string', 'max:100'],
            'city_code' => ['required', 'string', 'max:20'],
            'city_name' => ['required', 'string', 'max:100'],
            'barangay_code' => ['required', 'string', 'max:20'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ]);

        Hub::create(array_merge($validated, [
            'logistics_company_id' => $request->user()->id,
            'is_active' => true,
        ]));

        return redirect()->route('logistics.hubs.index')->with('status', "Hub '{$validated['name']}' created successfully.");
    }

    /**
     * Show the form for editing an existing hub.
     */
    public function edit(Request $request, Hub $hub): View
    {
        abort_unless((int) $hub->logistics_company_id === (int) $request->user()->id, 403);
        $provinces = PsgcProvince::orderBy('name')->get();

        return view('logistics.hubs.edit', compact('hub', 'provinces'));
    }

    /**
     * Update an existing hub.
     */
    public function update(Request $request, Hub $hub): RedirectResponse
    {
        abort_unless((int) $hub->logistics_company_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'unique:hubs,code,' . $hub->id],
            'province_code' => ['required', 'string', 'max:20'],
            'province_name' => ['required', 'string', 'max:100'],
            'city_code' => ['required', 'string', 'max:20'],
            'city_name' => ['required', 'string', 'max:100'],
            'barangay_code' => ['required', 'string', 'max:20'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ]);

        $hub->update($validated);

        return redirect()->route('logistics.hubs.index')->with('status', "Hub '{$hub->name}' updated successfully.");
    }

    /**
     * Toggle hub active status.
     */
    public function toggleStatus(Request $request, Hub $hub): RedirectResponse
    {
        abort_unless((int) $hub->logistics_company_id === (int) $request->user()->id, 403);

        $hub->update(['is_active' => ! $hub->is_active]);
        $statusText = $hub->is_active ? 'activated' : 'deactivated';

        return back()->with('status', "Hub '{$hub->name}' has been {$statusText}.");
    }
}

