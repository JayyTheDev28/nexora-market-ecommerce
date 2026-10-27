<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function edit(Request $request): View
    {
        $profile = $request->user()->sellerProfile;

        return view('seller.store.edit', compact('profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $updates = [
            'business_name' => $validated['business_name'],
            'description' => $validated['description'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($profile && $profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $updates['logo_path'] = $request->file('logo')->store('stores/logos', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($profile && $profile->banner_path) {
                Storage::disk('public')->delete($profile->banner_path);
            }
            $updates['banner_path'] = $request->file('banner')->store('stores/banners', 'public');
        }

        if ($profile) {
            $profile->update($updates);
        } else {
            $user->sellerProfile()->create(array_merge($updates, ['category' => 'general']));
        }

        return redirect()->route('seller.store.edit')
            ->with('status', 'Your store profile has been successfully updated.');
    }

    public function toggleVacation(Request $request): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;

        abort_unless($profile, 404, 'Seller profile not found.');

        $validated = $request->validate([
            'is_vacation_mode' => ['required', 'boolean'],
            'vacation_notice' => ['nullable', 'string', 'max:500'],
        ]);

        $profile->update([
            'is_vacation_mode' => $validated['is_vacation_mode'],
            'vacation_notice' => $validated['vacation_notice'] ?? null,
        ]);

        $statusMsg = $validated['is_vacation_mode']
            ? 'Vacation mode is now ON. Buyers will see your vacation notice and purchasing is temporarily paused.'
            : 'Vacation mode is now OFF. Your store is open for business and accepting new orders.';

        return redirect()->route('seller.store.edit')->with('status', $statusMsg);
    }
}
