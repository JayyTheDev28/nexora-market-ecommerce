<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
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
use Illuminate\Validation\Rules\Password;
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
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Normalize contact number if passed with Philippine international prefix (+63) or 10-digit format
        $rawContact = trim((string) $request->input('contact_number', ''));
        if (str_starts_with($rawContact, '+63')) {
            $rawContact = '0' . substr($rawContact, 3);
        } elseif (str_starts_with($rawContact, '63') && strlen($rawContact) === 12) {
            $rawContact = '0' . substr($rawContact, 2);
        } elseif (preg_match('/^9\d{9}$/', $rawContact)) {
            $rawContact = '0' . $rawContact;
        }

        $rawCategories = $request->input('categories');
        if (empty($rawCategories) && $request->filled('category')) {
            $rawCategories = is_array($request->input('category')) ? $request->input('category') : [$request->input('category')];
        }
        if (is_array($rawCategories)) {
            $rawCategories = array_map('strval', $rawCategories);
        } elseif (! empty($rawCategories)) {
            $rawCategories = [(string) $rawCategories];
        }

        $inputData = array_merge($request->all(), [
            'contact_number' => $rawContact,
            'province_code' => $request->input('province'),
            'municipality_code' => $request->input('municipality'),
            'barangay_code' => $request->input('barangay'),
            'categories' => $rawCategories,
        ]);

        $validator = Validator::make($inputData, [
            'role' => ['required', 'in:' . implode(',', self::REGISTERABLE_ROLES)],

            // Name: No special characters, 2 to 50 characters, no duplicate name
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\pL\s]+$/u',
                function ($attribute, $value, $fail) use ($request) {
                    $firstName = trim((string) $value);
                    $lastName = trim((string) $request->input('last_name', ''));
                    if ($firstName !== '' && $lastName !== '') {
                        if (strtolower($firstName) === strtolower($lastName)) {
                            $fail('First name and last name cannot be identical.');
                            return;
                        }
                        $exists = User::whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower($firstName)])
                            ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower($lastName)])
                            ->exists();
                        if ($exists) {
                            $fail('A user with this full name is already registered.');
                        }
                    }
                },
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\pL\s]+$/u',
            ],
            'middle_initial' => ['nullable', 'string', 'max:5', 'regex:/^[\pL\s]+$/u'],
            'sex' => ['required', 'in:male,female,other'],

            // Age & Birthday: Cannot be in 2026 or later, age cannot be 0
            'birthday' => [
                'required',
                'date',
                'before_or_equal:2025-12-31',
                function ($attribute, $value, $fail) {
                    try {
                        $date = \Carbon\Carbon::parse($value);
                        if ($date->year >= 2026) {
                            $fail('Birthday cannot be in the year 2026 or later.');
                            return;
                        }
                        if ($date->age <= 0) {
                            $fail('Age cannot be 0. Users must be at least 1 year old.');
                        }
                    } catch (\Exception $e) {
                        $fail('Please provide a valid birth date.');
                    }
                },
            ],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            // Contact number: Philippines mobile (+63), no special characters, exactly 11 numbers, no duplicates
            'contact_number' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
                'unique:users,contact_number',
            ],

            // Password: min 8, uppercase, lowercase, numbers, special characters
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

            'province_code' => ['required', 'string'],
            'province_name' => ['required', 'string'],
            'municipality_code' => ['required', 'string'],
            'municipality_name' => ['required', 'string'],
            'barangay_code' => ['required', 'string'],
            'barangay_name' => ['required', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:255'],

            'valid_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            // Business name: Unique across sellers and unique across sorting centers
            'business_name' => [
                'required_if:role,seller,sorting_center',
                'nullable',
                'string',
                'min:2',
                'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    $role = $request->input('role');
                    $name = trim((string) $value);
                    if ($name === '') return;

                    if ($role === 'seller') {
                        $exists = SellerProfile::whereRaw('LOWER(TRIM(business_name)) = ?', [strtolower($name)])->exists();
                        if ($exists) {
                            $fail('This seller business name has already been registered.');
                        }
                    } elseif ($role === 'sorting_center') {
                        $exists = SortingCenterProfile::whereRaw('LOWER(TRIM(business_name)) = ?', [strtolower($name)])->exists();
                        if ($exists) {
                            $fail('This logistics facility name has already been registered.');
                        }
                    }
                },
            ],
            'categories' => ['required_if:role,seller', 'nullable', 'array', 'min:1'],
            'categories.*' => ['string'],
            'category' => ['nullable', 'string'],
            'business_permit' => ['required_if:role,seller,sorting_center', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            // Required for couriers / riders per ERP specification
            'vehicle_type' => ['required_if:role,courier', 'nullable', 'string', 'max:255'],
            'plate_number' => ['required_if:role,courier', 'nullable', 'string', 'max:50'],
            'or_cr' => ['required_if:role,courier', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'terms' => ['accepted'],
        ], [
            'categories.required_if' => 'Please select at least one online shopping category for your store.',
            'categories.min' => 'Please select at least one online shopping category for your store.',
            'first_name.regex' => 'First name must contain letters and spaces only, with no special characters.',
            'first_name.min' => 'First name must be at least 2 characters.',
            'first_name.max' => 'First name may not be greater than 50 characters.',
            'last_name.regex' => 'Last name must contain letters and spaces only, with no special characters.',
            'last_name.min' => 'Last name must be at least 2 characters.',
            'last_name.max' => 'Last name may not be greater than 50 characters.',
            'middle_initial.regex' => 'Middle initial may only contain letters.',
            'birthday.before_or_equal' => 'Birthday cannot be in the year 2026 or later.',
            'contact_number.regex' => 'Contact number must be an 11-digit Philippine mobile number starting with 09 (e.g. 09171234567) with no special characters.',
            'contact_number.unique' => 'This contact number has already been registered.',
            'password.min' => 'Password must be at least 8 characters and include uppercase, lowercase, numbers, and special characters.',
        ]);

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

            $selectedCatInputs = (array) ($validated['categories'] ?? [$validated['category'] ?? 'General']);
            $selectedCats = Category::whereIn('id', $selectedCatInputs)
                ->orWhereIn('slug', $selectedCatInputs)
                ->get();

            if ($selectedCats->isNotEmpty()) {
                $user->allowedCategories()->sync($selectedCats->pluck('id'));
                $displayCategory = $selectedCats->pluck('name')->implode(', ');
            } else {
                $displayCategory = implode(', ', $selectedCatInputs);
            }

            SellerProfile::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'category' => $displayCategory ?: 'Retail & General Merchandise',
                'business_permit_path' => $permitPath,
            ]);
        } elseif ($validated['role'] === 'sorting_center') {
            $permitPath = $request->file('business_permit')->store('permits', 'public');
            SortingCenterProfile::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'business_permit_path' => $permitPath,
            ]);
        } elseif ($validated['role'] === 'courier') {
            $orCrPath = $request->file('or_cr') ? $request->file('or_cr')->store('couriers/or_cr', 'public') : null;
            CourierProfile::create([
                'user_id' => $user->id,
                'vehicle_type' => $validated['vehicle_type'],
                'plate_number' => $validated['plate_number'],
                'or_cr_path' => $orCrPath,
                'license_path' => $validIdPath,
            ]);
        }

        event(new Registered($user));
        // Note: Registered event triggers Laravel's SendEmailVerificationNotification listener,
        // which calls $user->sendEmailVerificationNotification().

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
