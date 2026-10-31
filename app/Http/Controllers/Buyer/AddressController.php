<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Store a new address for the buyer.
     * Enforces: Max 10 addresses per buyer, default address protection.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Enforce maximum 10 addresses per buyer (Rule B2)
        if ($user->addresses()->count() >= 10) {
            return back()->withErrors(['address' => 'You have reached the maximum limit of 10 addresses. Please delete an existing address to add a new one.']);
        }

        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'region_code' => ['nullable', 'string', 'max:50'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'province_code' => ['required', 'string', 'max:50'],
            'province_name' => ['required', 'string', 'max:100'],
            'municipality_code' => ['required', 'string', 'max:50'],
            'municipality_name' => ['required', 'string', 'max:100'],
            'barangay_code' => ['required', 'string', 'max:50'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $hasAddresses = $user->addresses()->exists();
        $isDefault = ! $hasAddresses || (! empty($validated['is_default']));

        DB::transaction(function () use ($user, $validated, $isDefault) {
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $user->addresses()->create(array_merge($validated, [
                'is_default' => $isDefault,
            ]));
        });

        return back()->with('status', 'Address added successfully.');
    }

    /**
     * Update an existing address.
     */
    public function update(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'region_code' => ['nullable', 'string', 'max:50'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'province_code' => ['required', 'string', 'max:50'],
            'province_name' => ['required', 'string', 'max:100'],
            'municipality_code' => ['required', 'string', 'max:50'],
            'municipality_name' => ['required', 'string', 'max:100'],
            'barangay_code' => ['required', 'string', 'max:50'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $address, $validated) {
            if (! empty($validated['is_default'])) {
                $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
                $address->is_default = true;
            }

            $address->update($validated);
        });

        return back()->with('status', 'Address updated successfully.');
    }

    /**
     * Set an address as default.
     */
    public function setDefault(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('status', 'Default address updated.');
    }

    /**
     * Delete an address.
     * Enforces: Default address protection.
     */
    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $user = $request->user();
        $totalAddresses = $user->addresses()->count();

        // If user has other addresses and this is the default, prevent deletion unless another is chosen first
        // Or automatically designate another address as default.
        if ($address->is_default && $totalAddresses > 1) {
            // Promote the most recently updated remaining address to default
            $nextDefault = $user->addresses()->where('id', '!=', $address->id)->latest('updated_at')->first();
            if ($nextDefault) {
                $nextDefault->update(['is_default' => true]);
            }
        }

        $address->delete();

        return back()->with('status', 'Address removed successfully.');
    }
}
