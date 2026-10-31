<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(): View
    {
        $admin = auth()->user();
        return view('admin.account.show', compact('admin'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'contact_number' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'contact_number')->ignore($admin->id)],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($admin->id)],
        ]);

        $old = [
            'first_name' => $admin->first_name,
            'last_name' => $admin->last_name,
            'contact_number' => $admin->contact_number,
            'email' => $admin->email,
        ];

        $admin->update($validated);

        AuditLog::log(
            action: 'admin_profile_updated',
            auditable: $admin,
            oldValues: $old,
            newValues: $validated,
            reason: 'Administrator updated personal profile details'
        );

        return back()->with('status', 'Your profile information has been successfully updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $admin = $request->user();

        if (! Hash::check($validated['current_password'], $admin->password)) {
            return back()->withErrors(['current_password' => 'The provided current password does not match our records.']);
        }

        $admin->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::log(
            action: 'admin_password_updated',
            auditable: $admin,
            oldValues: null,
            newValues: null,
            reason: 'Administrator changed account password'
        );

        return back()->with('status', 'Your password has been changed successfully.');
    }
}
