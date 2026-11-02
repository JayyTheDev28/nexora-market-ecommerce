<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\AddressRequest;
use App\Http\Resources\Api\Mobile\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * List all addresses for the buyer
     * GET /api/mobile/v1/buyer/addresses
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'data' => AddressResource::collection($addresses),
            'message' => 'Addresses retrieved successfully.',
        ]);
    }

    /**
     * Store a new address (enforcing max 10 addresses)
     * POST /api/mobile/v1/buyer/addresses
     */
    public function store(AddressRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->addresses()->count() >= 10) {
            return response()->json([
                'message' => 'You have reached the maximum limit of 10 addresses. Please delete an existing address to add a new one.',
                'errors' => ['address' => ['You have reached the maximum limit of 10 addresses.']],
            ], 422);
        }

        $validated = $request->validated();
        $hasAddresses = $user->addresses()->exists();
        $isDefault = ! $hasAddresses || (! empty($validated['is_default']));

        $address = DB::transaction(function () use ($user, $validated, $isDefault) {
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create(array_merge($validated, [
                'is_default' => $isDefault,
            ]));
        });

        return response()->json([
            'data' => new AddressResource($address),
            'message' => 'Address added successfully.',
        ], 201);
    }

    /**
     * Update an existing address
     * PUT /api/mobile/v1/buyer/addresses/{address}
     */
    public function update(AddressRequest $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $address, $validated) {
            if (! empty($validated['is_default'])) {
                $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
                $address->is_default = true;
            }

            $address->update($validated);
        });

        return response()->json([
            'data' => new AddressResource($address->fresh()),
            'message' => 'Address updated successfully.',
        ]);
    }

    /**
     * Set address as default
     * POST /api/mobile/v1/buyer/addresses/{address}/default
     */
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'data' => new AddressResource($address->fresh()),
            'message' => 'Default address updated.',
        ]);
    }

    /**
     * Delete an address (with default protection)
     * DELETE /api/mobile/v1/buyer/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = $request->user();
        $totalAddresses = $user->addresses()->count();

        if ($address->is_default && $totalAddresses > 1) {
            $nextDefault = $user->addresses()->where('id', '!=', $address->id)->latest('updated_at')->first();
            if ($nextDefault) {
                $nextDefault->update(['is_default' => true]);
            }
        }

        $address->delete();

        return response()->json([
            'data' => null,
            'message' => 'Address removed successfully.',
        ]);
    }
}

