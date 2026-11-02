<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use App\Models\ZoneBarangay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationApiController extends Controller
{
    /**
     * List all official provinces.
     */
    public function provinces(): JsonResponse
    {
        $provinces = PsgcProvince::orderBy('name')->get(['code', 'name', 'region_code', 'region_name']);
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
        return response()->json($cities);
    }

    /**
     * List barangays in a city/municipality, with coverage annotation for a logistics company.
     */
    public function barangays(Request $request, string $cityCode): JsonResponse
    {
        $companyId = $request->query('logistics_company_id', $request->user()?->id);
        $excludeZoneId = $request->query('exclude_zone_id');

        $barangays = PsgcBarangay::where('city_code', $cityCode)
            ->orderBy('name')
            ->get(['code', 'name', 'city_code', 'city_name', 'province_code']);

        // Check which barangays are already covered by this logistics company
        $coveredBarangayMap = collect();
        if ($companyId) {
            $coveredQuery = ZoneBarangay::where('logistics_company_id', $companyId)
                ->where('city_code', $cityCode)
                ->with('zone:id,name');

            if ($excludeZoneId) {
                $coveredQuery->where('zone_id', '!=', $excludeZoneId);
            }

            $coveredBarangayMap = $coveredQuery->get()->keyBy('barangay_code');
        }

        $annotated = $barangays->map(function ($b) use ($coveredBarangayMap) {
            $coverage = $coveredBarangayMap->get($b->code);
            return [
                'code' => $b->code,
                'name' => $b->name,
                'city_code' => $b->city_code,
                'city_name' => $b->city_name,
                'province_code' => $b->province_code,
                'is_covered' => $coverage !== null,
                'covered_by_zone_id' => $coverage?->zone_id,
                'covered_by_zone_name' => $coverage?->zone?->name,
            ];
        });

        return response()->json($annotated);
    }
}
