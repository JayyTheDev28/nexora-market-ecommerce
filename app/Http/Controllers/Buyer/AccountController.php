<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('address');

        return view('buyer.account.index', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'sex' => ['required', 'in:male,female,other'],
            'birthday' => ['required', 'date', 'before:today'],
            'contact_number' => ['required', 'string', 'max:20'],
        ]);

        $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])->age;

        $request->user()->update($validated);

        return back()->with('status', 'Your profile has been updated.');
    }

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

        return back()->with('status', 'Your address has been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Your password has been changed.');
    }
}
