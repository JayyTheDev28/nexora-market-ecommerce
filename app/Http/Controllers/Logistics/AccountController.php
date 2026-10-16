<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\SortingCenterProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Display sorting center profile and settings.
     */
    public function show(Request $request): View
    {
        $user = $request->user()->load(['sortingCenterProfile', 'address']);

        return view('logistics.account.index', compact('user'));
    }

    /**
     * Update permitted personal and facility information.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();

        // Update user base fields (safe list, protecting role and statuses)
        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'contact_number' => $validated['contact_number'],
        ]);

        // Update facility business name
        SortingCenterProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['business_name' => $validated['business_name']]
        );

        return back()->with('status', 'Facility profile updated successfully.');
    }

    /**
     * Update facility address.
     */
    public function updateAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'province_code' => ['required', 'string'],
            'province_name' => ['required', 'string'],
            'municipality_code' => ['required', 'string'],
            'municipality_name' => ['required', 'string'],
            'barangay_code' => ['required', 'string'],
            'barangay_name' => ['required', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:255'],
        ]);

        Address::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return back()->with('status', 'Facility address updated successfully.');
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Password changed successfully.');
    }
}
