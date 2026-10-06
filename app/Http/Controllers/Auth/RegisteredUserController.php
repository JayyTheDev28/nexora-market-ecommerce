<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use App\Support\SampleCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Registerable roles from this public form. Riders/couriers register
     * through a separate flow (not built yet), and admins are never
     * self-registered.
     */
    private const REGISTERABLE_ROLES = ['buyer', 'seller', 'sorting_center'];

    public function create(): View
    {
        return view('buyer.auth.register', [
            'hideFooter' => true,
            'categories' => SampleCatalog::categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => ['required', 'in:' . implode(',', self::REGISTERABLE_ROLES)],

            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'sex' => ['required', 'in:male,female,other'],
            'birthday' => ['required', 'date', 'before:today'],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['required', 'string', 'max:20'],

            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'province_code' => ['required', 'string'],
            'province_name' => ['required', 'string'],
            'municipality_code' => ['required', 'string'],
            'municipality_name' => ['required', 'string'],
            'barangay_code' => ['required', 'string'],
            'barangay_name' => ['required', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:255'],

            'valid_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            // Only required when role is seller/sorting_center — enforced
            // below via a conditional validator since the field set differs
            // per role and Laravel's `required_if` reads cleanly for that.
            'business_name' => ['required_if:role,seller,sorting_center', 'nullable', 'string', 'max:255'],
            'category' => ['required_if:role,seller', 'nullable', 'string'],
            'business_permit' => ['required_if:role,seller,sorting_center', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'terms' => ['accepted'],
        ]);

        // The select elements only carry PSGC codes as their `name`
        // attributes (province/municipality/barangay); the resolved names
        // travel in hidden inputs. Normalize both onto *_code / *_name here
        // since the raw request uses the select's bare field name for the code.
        $validator->setData(array_merge($request->all(), [
            'province_code' => $request->input('province'),
            'municipality_code' => $request->input('municipality'),
            'barangay_code' => $request->input('barangay'),
        ]));

        $validated = $validator->validate();

        // Age is computed server-side rather than trusted from the client.
        $age = \Carbon\Carbon::parse($validated['birthday'])->age;

        $validIdPath = $request->file('valid_id')->store('ids', 'public');

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'birthday' => $validated['birthday'],
            'age' => $age,
            'email' => $validated['email'],
            'contact_number' => $validated['contact_number'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'approval_status' => 'pending',
            'valid_id_path' => $validIdPath,
        ]);

        Address::create([
            'user_id' => $user->id,
            'province_code' => $validated['province_code'],
            'province_name' => $validated['province_name'],
            'municipality_code' => $validated['municipality_code'],
            'municipality_name' => $validated['municipality_name'],
            'barangay_code' => $validated['barangay_code'],
            'barangay_name' => $validated['barangay_name'],
            'street' => $validated['street'] ?? null,
            'house_number' => $validated['house_number'] ?? null,
        ]);

        if ($validated['role'] === 'seller') {
            $permitPath = $request->file('business_permit')->store('permits', 'public');
            SellerProfile::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'category' => $validated['category'],
                'business_permit_path' => $permitPath,
            ]);
        } elseif ($validated['role'] === 'sorting_center') {
            $permitPath = $request->file('business_permit')->store('permits', 'public');
            SortingCenterProfile::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'business_permit_path' => $permitPath,
            ]);
        }

        event(new Registered($user));
        // Registered's default listener only fires if wired up in the app's
        // event discovery; sending explicitly here guarantees it happens
        // regardless of that configuration.
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
