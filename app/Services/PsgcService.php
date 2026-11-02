<?php

namespace App\Services;

use App\Models\Address;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use Illuminate\Support\Collection;

class PsgcService
{
    /**
     * Get all active official provinces.
     */
    public function getProvinces(): Collection
    {
        return PsgcProvince::orderBy('name')->get();
    }

    /**
     * Get cities/municipalities for a given province.
     */
    public function getCitiesByProvince(string $provinceCode): Collection
    {
        return PsgcCity::where('province_code', $provinceCode)->orderBy('name')->get();
    }

    /**
     * Get barangays for a given city/municipality.
     */
    public function getBarangaysByCity(string $cityCode): Collection
    {
        return PsgcBarangay::where('city_code', $cityCode)->orderBy('name')->get();
    }

    /**
     * Find a barangay by official PSGC code.
     */
    public function findBarangay(string $barangayCode): ?PsgcBarangay
    {
        return PsgcBarangay::where('code', $barangayCode)->first();
    }

    /**
     * Match and backfill an address against official PSGC records.
     * Flags for manual review if no match is found.
     */
    public function matchAndBackfillAddress(Address $address): Address
    {
        // 1. Direct code lookup
        $matched = null;
        if (! empty($address->barangay_code)) {
            $matched = PsgcBarangay::where('code', $address->barangay_code)->first();
        }

        // 2. Fallback: match by normalized names if code lookup failed
        if (! $matched && ! empty($address->barangay_name)) {
            $matched = PsgcBarangay::where(function ($q) use ($address) {
                $cleanBrgy = trim(preg_replace('/\s+/', ' ', strtolower($address->barangay_name)));
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$cleanBrgy]);
            })->when(! empty($address->municipality_name), function ($q) use ($address) {
                $cleanCity = trim(preg_replace('/\s+/', ' ', strtolower($address->municipality_name)));
                $q->whereRaw('LOWER(TRIM(city_name)) LIKE ?', ["%{$cleanCity}%"]);
            })->first();
        }

        if ($matched) {
            $address->update([
                'province_code' => $matched->province_code,
                'province_name' => $matched->province?->name ?? $address->province_name,
                'municipality_code' => $matched->city_code,
                'municipality_name' => $matched->city_name,
                'barangay_code' => $matched->code,
                'barangay_name' => $matched->name,
                'needs_manual_review' => false,
                'manual_review_reason' => null,
            ]);
        } else {
            // Cannot reliably match official PSGC: flag for manual review
            $address->update([
                'needs_manual_review' => true,
                'manual_review_reason' => 'Official PSGC code or barangay could not be verified automatically in registry',
            ]);
        }

        return $address;
    }

    /**
     * Backfill all existing addresses in the database.
     */
    public function backfillAllAddresses(): array
    {
        $addresses = Address::all();
        $matchedCount = 0;
        $flaggedCount = 0;

        foreach ($addresses as $address) {
            $this->matchAndBackfillAddress($address);
            if ($address->needs_manual_review) {
                $flaggedCount++;
            } else {
                $matchedCount++;
            }
        }

        return [
            'total' => $addresses->count(),
            'matched' => $matchedCount,
            'flagged_for_review' => $flaggedCount,
        ];
    }
}

