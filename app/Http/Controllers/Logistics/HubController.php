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
            $hub->parcels_at_hub_count = Order::where('current_custody_logistics_id', $companyId)
                ->where(function ($q) use ($hub) {
                    $q->where('origin_hub_id', $hub->id)
                      ->orWhere('destination_hub_id', $hub->id);
                })
                ->whereIn('status', ['received_at_hub', 'at_sorting_center', 'sorted'])
                ->count();
            return $hub;
        });

        return view('logistics.hubs.index', compact('hubs'));
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

