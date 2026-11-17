<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
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
     * Resolve the assigned province for a logistics company account.
     */
    protected function resolveCompanyProvince(User $company): array
    {
        $code = $company->address?->province_code;
        $name = $company->address?->province_name;

        if (! $code) {
            $hub = $company->hubs()->first();
            $code = $hub?->province_code;
            $name = $hub?->province_name;
        }

        if (! $code && $company->logisticsProfile?->province) {
            $prov = PsgcProvince::where('name', 'like', '%' . $company->logisticsProfile->province . '%')->first();
            $code = $prov?->code;
            $name = $prov?->name;
        }

        return [
            'code' => $code ?: '043400000',
            'name' => $name ?: 'Laguna',
        ];
    }

    /**
     * Show the form for creating a new zone.
     */
    public function create(Request $request): View
    {
        $company = $request->user();
        $companyId = $company->id;
        $hubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->get();

        $province = $this->resolveCompanyProvince($company);
        $companyProvinceCode = $province['code'];
        $companyProvinceName = $province['name'];

        // Pre-fetch all cities for the company's operating province (supports any province in the Philippines)
        $cities = PsgcCity::where('province_code', $companyProvinceCode)
            ->orderBy('name')
            ->get(['code', 'name', 'province_code', 'province_name']);

        if ($cities->isEmpty()) {
            $cities = app(\App\Http\Controllers\Api\LocationApiController::class)->syncCitiesForProvince($companyProvinceCode);
        }

        if ($cities->isEmpty()) {
            $cities = app(\App\Http\Controllers\Api\LocationApiController::class)->syncCitiesForProvince($companyProvinceCode);
        }

        $riders = User::where('role', 'rider')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->where('approval_status', 'approved')
            ->orderBy('first_name')
            ->get();

        return view('logistics.zones.create', compact('hubs', 'companyProvinceCode', 'companyProvinceName', 'cities', 'riders'));
    }

    /**
     * Store a newly created zone with selected barangays and assigned riders.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = $request->user();
        $companyId = $company->id;
        $province = $this->resolveCompanyProvince($company);

        // Enforce province locked to company account
        if (! $request->filled('province_code')) {
            $request->merge([
                'province_code' => $province['code'],
                'province_name' => $province['name'],
            ]);
        }

        if ($request->filled('city_code') && ! $request->filled('city_name')) {
            $cityObj = PsgcCity::where('code', $request->input('city_code'))->first();
            if ($cityObj) {
                $request->merge(['city_name' => $cityObj->name]);
            }
        }

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

        $this->validateHubCityAndRiders($companyId, $validated);

        // Enforce: Area exclusivity across all logistics providers ("Must not have a same logistics within the same area")
        $existingCoverage = ZoneBarangay::whereIn('barangay_code', $validated['barangay_codes'])
            ->with(['zone', 'logisticsCompany.logisticsProfile', 'logisticsCompany'])
            ->get();

        if ($existingCoverage->isNotEmpty()) {
            $conflicts = $existingCoverage->map(function ($c) use ($companyId) {
                if ((int) $c->logistics_company_id === (int) $companyId) {
                    return "{$c->barangay_name} (already in your zone '{$c->zone?->name}')";
                }
                $compName = $c->logisticsCompany?->logisticsProfile?->company_name ?? $c->logisticsCompany?->name ?? 'another logistics provider';
                return "{$c->barangay_name} (already served by {$compName})";
            })->implode(', ');

            throw ValidationException::withMessages([
                'barangay_codes' => "Area exclusivity violation: The following barangays are already covered: {$conflicts}",
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

            // Assign riders cleanly (strictly 1 zone per rider; replaces any previous zone)
            $this->syncSingleZoneRiders($zone, $validated['rider_ids'] ?? []);
        });

        return redirect()->route('logistics.zones.index')->with('status', "Coverage Zone '{$validated['name']}' created successfully.");
    }

    /**
     * Show the form for editing an existing zone.
     */
    public function edit(Request $request, Zone $zone): View
    {
        $company = $request->user();
        $companyId = $company->id;
        abort_unless((int) $zone->logistics_company_id === (int) $companyId, 403);

        $hubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->get();

        $province = $this->resolveCompanyProvince($company);
        $companyProvinceCode = $zone->province_code ?: $province['code'];
        $companyProvinceName = $zone->province_name ?: $province['name'];

        $cities = PsgcCity::where('province_code', $companyProvinceCode)
            ->orderBy('name')
            ->get(['code', 'name', 'province_code', 'province_name']);

        if ($cities->isEmpty()) {
            $cities = app(\App\Http\Controllers\Api\LocationApiController::class)->syncCitiesForProvince($companyProvinceCode);
        }

        $riders = User::where('role', 'rider')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->where('approval_status', 'approved')
            ->orderBy('first_name')
            ->get();

        $selectedBarangayCodes = $zone->barangays()->pluck('barangay_code')->all();
        $selectedRiderIds = $zone->riders()->pluck('users.id')->all();

        return view('logistics.zones.edit', compact('zone', 'hubs', 'companyProvinceCode', 'companyProvinceName', 'cities', 'riders', 'selectedBarangayCodes', 'selectedRiderIds'));
    }

    /**
     * Update an existing zone and its coverage.
     */
    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $company = $request->user();
        $companyId = $company->id;
        abort_unless((int) $zone->logistics_company_id === (int) $companyId, 403);

        $province = $this->resolveCompanyProvince($company);
        if (! $request->filled('province_code')) {
            $request->merge([
                'province_code' => $zone->province_code ?: $province['code'],
                'province_name' => $zone->province_name ?: $province['name'],
            ]);
        }

        if ($request->filled('city_code') && ! $request->filled('city_name')) {
            $cityObj = PsgcCity::where('code', $request->input('city_code'))->first();
            if ($cityObj) {
                $request->merge(['city_name' => $cityObj->name]);
            }
        }

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

        $this->validateHubCityAndRiders($companyId, $validated);

        // Enforce: Area exclusivity across all logistics providers (excluding this zone)
        $existingCoverage = ZoneBarangay::where('zone_id', '!=', $zone->id)
            ->whereIn('barangay_code', $validated['barangay_codes'])
            ->with(['zone', 'logisticsCompany.logisticsProfile', 'logisticsCompany'])
            ->get();

        if ($existingCoverage->isNotEmpty()) {
            $conflicts = $existingCoverage->map(function ($c) use ($companyId) {
                if ((int) $c->logistics_company_id === (int) $companyId) {
                    return "{$c->barangay_name} (already in your zone '{$c->zone?->name}')";
                }
                $compName = $c->logisticsCompany?->logisticsProfile?->company_name ?? $c->logisticsCompany?->name ?? 'another logistics provider';
                return "{$c->barangay_name} (already served by {$compName})";
            })->implode(', ');

            throw ValidationException::withMessages([
                'barangay_codes' => "Area exclusivity violation: The following barangays are already covered: {$conflicts}",
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

            // Sync riders (strictly 1 zone per rider)
            $this->syncSingleZoneRiders($zone, $validated['rider_ids'] ?? [], detachRemoved: true);
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

    protected function validateHubCityAndRiders(int $companyId, array $validated): void
    {
        if (! empty($validated['hub_id'])) {
            $hub = Hub::where('id', $validated['hub_id'])
                ->where('logistics_company_id', $companyId)
                ->first();

            if (! $hub) {
                throw ValidationException::withMessages([
                    'hub_id' => 'Selected hub does not belong to your logistics company.',
                ]);
            }

            if ($hub->province_code !== $validated['province_code'] || $hub->city_code !== $validated['city_code']) {
                throw ValidationException::withMessages([
                    'hub_id' => "Zone city/province ({$validated['city_name']}, {$validated['province_name']}) must match hub {$hub->name} ({$hub->city_name}, {$hub->province_name}).",
                ]);
            }
        }

        if (! empty($validated['rider_ids'])) {
            $validCount = User::whereIn('id', $validated['rider_ids'])
                ->where('role', 'rider')
                ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
                ->count();

            if ($validCount !== count(array_unique($validated['rider_ids']))) {
                throw ValidationException::withMessages([
                    'rider_ids' => 'Assigned riders must be active delivery riders belonging to your logistics company.',
                ]);
            }
        }
    }

    protected function syncSingleZoneRiders(Zone $zone, array $riderIds, bool $detachRemoved = false): void
    {
        $cleanIds = array_values(array_unique(array_map('intval', $riderIds)));

        if ($detachRemoved) {
            if (empty($cleanIds)) {
                $zone->riders()->detach();
            } else {
                $zone->riders()->whereNotIn('users.id', $cleanIds)->detach();
            }
        }

        if (empty($cleanIds)) {
            return;
        }

        $riders = User::whereIn('id', $cleanIds)->get();
        foreach ($riders as $rider) {
            $rider->assignedZones()->sync([$zone->id]);
        }
    }
}

