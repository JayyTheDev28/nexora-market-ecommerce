<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\PsgcBarangay;
use App\Models\PsgcProvince;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ZoneController extends Controller
{
    /**
     * Display a listing of coverage zones.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->id;

        $zones = Zone::where('logistics_company_id', $companyId)
            ->with(['hub', 'barangays', 'riders'])
            ->withCount(['barangays', 'riders'])
            ->latest()
            ->paginate(15);

        $hubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->get();

        return view('logistics.zones.index', compact('zones', 'hubs'));
    }

    /**
     * Show the form for creating a new zone.
     */
    public function create(Request $request): View
    {
        $companyId = $request->user()->id;
        $hubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->get();
        $provinces = PsgcProvince::orderBy('name')->get();
        $riders = User::whereIn('role', ['rider', 'courier'])
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->where('approval_status', 'approved')
            ->orderBy('first_name')
            ->get();

        return view('logistics.zones.create', compact('hubs', 'provinces', 'riders'));
    }

    /**
     * Store a newly created zone with selected barangays and assigned riders.
     */
    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'hub_id' => ['nullable', 'exists:hubs,id'],
            'province_code' => ['required', 'string', 'max:20'],
            'province_name' => ['required', 'string', 'max:100'],
            'city_code' => ['required', 'string', 'max:20'],
            'city_name' => ['required', 'string', 'max:100'],
            'barangay_codes' => ['required', 'array', 'min:1'],
            'barangay_codes.*' => ['string'],
            'rider_ids' => ['nullable', 'array'],
            'rider_ids.*' => ['exists:users,id'],
        ]);

        // Enforce: Coverage must live in one place only per logistics company
        $existingCoverage = ZoneBarangay::where('logistics_company_id', $companyId)
            ->whereIn('barangay_code', $validated['barangay_codes'])
            ->with('zone')
            ->get();

        if ($existingCoverage->isNotEmpty()) {
            $conflicts = $existingCoverage->map(fn ($c) => "{$c->barangay_name} (already in zone '{$c->zone?->name}')")->implode(', ');
            throw ValidationException::withMessages([
                'barangay_codes' => "Coverage must live in one place only. The following barangays are already covered by other zones of your company: {$conflicts}",
            ]);
        }

        DB::transaction(function () use ($validated, $companyId) {
            $zone = Zone::create([
                'logistics_company_id' => $companyId,
                'hub_id' => $validated['hub_id'] ?? null,
                'name' => $validated['name'],
                'province_code' => $validated['province_code'],
                'province_name' => $validated['province_name'],
                'city_code' => $validated['city_code'],
                'city_name' => $validated['city_name'],
                'is_active' => true,
            ]);

            // Attach barangays
            foreach ($validated['barangay_codes'] as $bCode) {
                $psgc = PsgcBarangay::where('code', $bCode)->first();
                ZoneBarangay::create([
                    'zone_id' => $zone->id,
                    'logistics_company_id' => $companyId,
                    'province_code' => $validated['province_code'],
                    'province_name' => $validated['province_name'],
                    'city_code' => $validated['city_code'],
                    'city_name' => $validated['city_name'],
                    'barangay_code' => $bCode,
                    'barangay_name' => $psgc?->name ?? $bCode,
                ]);
            }

            // Attach assigned riders
            if (! empty($validated['rider_ids'])) {
                $zone->riders()->sync($validated['rider_ids']);
            }
        });

        return redirect()->route('logistics.zones.index')->with('status', "Coverage Zone '{$validated['name']}' created successfully.");
    }

    /**
     * Show the form for editing an existing zone.
     */
    public function edit(Request $request, Zone $zone): View
    {
        $companyId = $request->user()->id;
        abort_unless((int) $zone->logistics_company_id === (int) $companyId, 403);

        $hubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->get();
        $provinces = PsgcProvince::orderBy('name')->get();
        $riders = User::whereIn('role', ['rider', 'courier'])
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->where('approval_status', 'approved')
            ->orderBy('first_name')
            ->get();

        $selectedBarangayCodes = $zone->barangays()->pluck('barangay_code')->all();
        $selectedRiderIds = $zone->riders()->pluck('users.id')->all();

        return view('logistics.zones.edit', compact('zone', 'hubs', 'provinces', 'riders', 'selectedBarangayCodes', 'selectedRiderIds'));
    }

    /**
     * Update an existing zone and its coverage.
     */
    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $companyId = $request->user()->id;
        abort_unless((int) $zone->logistics_company_id === (int) $companyId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'hub_id' => ['nullable', 'exists:hubs,id'],
            'province_code' => ['required', 'string', 'max:20'],
            'province_name' => ['required', 'string', 'max:100'],
            'city_code' => ['required', 'string', 'max:20'],
            'city_name' => ['required', 'string', 'max:100'],
            'barangay_codes' => ['required', 'array', 'min:1'],
            'barangay_codes.*' => ['string'],
            'rider_ids' => ['nullable', 'array'],
            'rider_ids.*' => ['exists:users,id'],
        ]);

        // Enforce: Coverage must live in one place only per logistics company (excluding this zone)
        $existingCoverage = ZoneBarangay::where('logistics_company_id', $companyId)
            ->where('zone_id', '!=', $zone->id)
            ->whereIn('barangay_code', $validated['barangay_codes'])
            ->with('zone')
            ->get();

        if ($existingCoverage->isNotEmpty()) {
            $conflicts = $existingCoverage->map(fn ($c) => "{$c->barangay_name} (already in zone '{$c->zone?->name}')")->implode(', ');
            throw ValidationException::withMessages([
                'barangay_codes' => "Coverage must live in one place only. The following barangays are already covered by another zone: {$conflicts}",
            ]);
        }

        DB::transaction(function () use ($zone, $validated, $companyId) {
            $zone->update([
                'hub_id' => $validated['hub_id'] ?? null,
                'name' => $validated['name'],
                'province_code' => $validated['province_code'],
                'province_name' => $validated['province_name'],
                'city_code' => $validated['city_code'],
                'city_name' => $validated['city_name'],
            ]);

            // Sync barangays
            $zone->barangays()->delete();
            foreach ($validated['barangay_codes'] as $bCode) {
                $psgc = PsgcBarangay::where('code', $bCode)->first();
                ZoneBarangay::create([
                    'zone_id' => $zone->id,
                    'logistics_company_id' => $companyId,
                    'province_code' => $validated['province_code'],
                    'province_name' => $validated['province_name'],
                    'city_code' => $validated['city_code'],
                    'city_name' => $validated['city_name'],
                    'barangay_code' => $bCode,
                    'barangay_name' => $psgc?->name ?? $bCode,
                ]);
            }

            // Sync riders
            $zone->riders()->sync($validated['rider_ids'] ?? []);
        });

        return redirect()->route('logistics.zones.index')->with('status', "Zone '{$zone->name}' updated successfully.");
    }

    /**
     * Delete an existing zone.
     */
    public function destroy(Request $request, Zone $zone): RedirectResponse
    {
        $companyId = $request->user()->id;
        abort_unless((int) $zone->logistics_company_id === (int) $companyId, 403);

        $name = $zone->name;
        $zone->delete();

        return redirect()->route('logistics.zones.index')->with('status', "Zone '{$name}' deleted successfully.");
    }
}

