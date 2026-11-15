<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use App\Models\ZoneBarangay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationApiController extends Controller
{
    /**
     * List all official provinces.
     */
    public function provinces(): JsonResponse
    {
        $provinces = PsgcProvince::orderBy('name')->get(['code', 'name', 'region_code', 'region_name']);

        if ($provinces->count() < 80) {
            $this->syncAllProvinces();
            $provinces = PsgcProvince::orderBy('name')->get(['code', 'name', 'region_code', 'region_name']);
        }

        return response()->json($provinces);
    }

    /**
     * List cities/municipalities in a province.
     */
    public function cities(string $provinceCode): JsonResponse
    {
        $cities = PsgcCity::where('province_code', $provinceCode)
            ->orderBy('name')
            ->get(['code', 'name', 'province_code', 'province_name']);

        if ($cities->isEmpty()) {
            $cities = $this->syncCitiesForProvince($provinceCode);
        }

        return response()->json($cities);
    }

    /**
     * List barangays in a city/municipality, with coverage annotation for logistics companies.
     * Enforces area uniqueness: a barangay covered by any logistics company is marked as covered.
     */
    public function barangays(Request $request, string $cityCode): JsonResponse
    {
        $companyId = $request->query('logistics_company_id', $request->user()?->id);
        $excludeZoneId = $request->query('exclude_zone_id');

        $barangays = PsgcBarangay::where('city_code', $cityCode)
            ->orderBy('name')
            ->get(['code', 'name', 'city_code', 'city_name', 'province_code']);

        if ($barangays->isEmpty()) {
            $this->syncBarangaysForCity($cityCode);
            $barangays = PsgcBarangay::where('city_code', $cityCode)
                ->orderBy('name')
                ->get(['code', 'name', 'city_code', 'city_name', 'province_code']);
        }

        // Check which barangays are already covered across any logistics company
        $coveredQuery = ZoneBarangay::where('city_code', $cityCode)
            ->with(['zone:id,name,logistics_company_id', 'logisticsCompany:id,first_name,last_name']);

        if ($excludeZoneId) {
            $coveredQuery->where('zone_id', '!=', $excludeZoneId);
        }

        $coveredBarangayMap = $coveredQuery->get()->keyBy('barangay_code');

        $annotated = $barangays->map(function ($b) use ($coveredBarangayMap, $companyId) {
            $coverage = $coveredBarangayMap->get($b->code);
            $isCovered = $coverage !== null;
            $isOtherCompany = $isCovered && $companyId && (int) $coverage->logistics_company_id !== (int) $companyId;

            $coveredByLabel = null;
            if ($isCovered) {
                if ($isOtherCompany) {
                    $otherName = $coverage->logisticsCompany?->full_name ?? 'Another Logistics Partner';
                    $coveredByLabel = "Covered by {$otherName}";
                } else {
                    $coveredByLabel = $coverage->zone?->name ?? 'Existing Zone';
                }
            }

            return [
                'code' => $b->code,
                'name' => $b->name,
                'city_code' => $b->city_code,
                'city_name' => $b->city_name,
                'province_code' => $b->province_code,
                'is_covered' => $isCovered,
                'is_other_company' => $isOtherCompany,
                'covered_by_zone_id' => $coverage?->zone_id,
                'covered_by_zone_name' => $coveredByLabel,
            ];
        });

        return response()->json($annotated);
    }

    /**
     * Sync and persist all 82 Philippine provinces from the official PSGC API.
     */
    public function syncAllProvinces(): void
    {
        try {
            $res = Http::withoutVerifying()->timeout(10)->get('https://psgc.gitlab.io/api/provinces/');
            if ($res->successful()) {
                $now = now();
                $rows = collect($res->json())->map(function ($p) use ($now) {
                    return [
                        'code' => (string) $p['code'],
                        'name' => $p['name'],
                        'region_code' => (string) ($p['regionCode'] ?? '000000000'),
                        'region_name' => (string) ($p['regionCode'] ?? 'Philippines'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                if (! empty($rows)) {
                    PsgcProvince::upsert($rows, ['code'], ['name', 'region_code', 'region_name', 'updated_at']);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to sync provinces from PSGC API: ' . $e->getMessage());
        }
    }

    /**
     * Sync and persist cities for any province in the Philippines on-demand from the official PSGC API.
     */
    public function syncCitiesForProvince(string $provinceCode): Collection
    {
        try {
            $prov = PsgcProvince::where('code', $provinceCode)->first();
            $provName = $prov?->name ?? 'Unknown Province';

            // Support Metro Manila / NCR or provincial codes
            $isNcr = $provinceCode === '133900000' || str_starts_with($provinceCode, '13');
            $url = $isNcr
                ? 'https://psgc.gitlab.io/api/regions/130000000/cities-municipalities/'
                : "https://psgc.gitlab.io/api/provinces/{$provinceCode}/cities-municipalities/";

            $res = Http::withoutVerifying()->timeout(10)->get($url);
            if ($res->successful()) {
                $now = now();
                $rows = collect($res->json())->map(function ($item) use ($provinceCode, $provName, $now) {
                    return [
                        'code' => (string) $item['code'],
                        'name' => $item['name'],
                        'province_code' => (string) $provinceCode,
                        'province_name' => $provName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                if (! empty($rows)) {
                    PsgcCity::upsert($rows, ['code'], ['name', 'province_code', 'province_name', 'updated_at']);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to sync cities from PSGC API for province {$provinceCode}: " . $e->getMessage());
        }

        return PsgcCity::where('province_code', $provinceCode)
            ->orderBy('name')
            ->get(['code', 'name', 'province_code', 'province_name']);
    }

    /**
     * Sync and persist barangays for any city in the Philippines on-demand from the official PSGC API.
     */
    public function syncBarangaysForCity(string $cityCode): void
    {
        try {
            $city = PsgcCity::where('code', $cityCode)->first();
            $cityName = $city?->name ?? 'Unknown City';
            $provinceCode = $city?->province_code ?? '000000000';

            $res = Http::withoutVerifying()->timeout(10)->get("https://psgc.gitlab.io/api/cities-municipalities/{$cityCode}/barangays/");
            if ($res->successful()) {
                $now = now();
                $rows = collect($res->json())->map(function ($item) use ($cityCode, $cityName, $provinceCode, $now) {
                    return [
                        'code' => (string) $item['code'],
                        'name' => $item['name'],
                        'city_code' => (string) $cityCode,
                        'city_name' => $cityName,
                        'province_code' => (string) $provinceCode,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                if (! empty($rows)) {
                    PsgcBarangay::upsert($rows, ['code'], ['name', 'city_code', 'city_name', 'province_code', 'updated_at']);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to sync barangays from PSGC API for city {$cityCode}: " . $e->getMessage());
        }
    }
}

