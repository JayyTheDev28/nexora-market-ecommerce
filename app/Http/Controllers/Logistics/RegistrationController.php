<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsProfile;
use App\Models\Otp;
use App\Models\SortingCenterProfile;
use App\Models\User;
use App\Notifications\LogisticsOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Show the Logistics Partner Registration form.
     */
    public function showRegistrationForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isSortingCenter()) {
                $profile = $user->logisticsProfile;
                if ($profile?->isRejected() || $user->isDisapproved()) {
                    return redirect()->route('logistics.resubmit');
                }
                if ($profile?->isPendingApproval() || $user->isPending()) {
                    return redirect()->route('logistics.pending');
                }
                if ($profile?->isApproved() && $user->isApproved()) {
                    return redirect()->route('logistics.dashboard');
                }
            }
        }

        return view('logistics.auth.register');
    }

    /**
     * Handle registration submission.
     */
    public function register(Request $request): RedirectResponse
    {
        // 1. Sanitize string inputs
        $cleanEmail = strtolower(trim((string) $request->input('email', '')));
        $cleanPhone = trim((string) $request->input('mobile_number', ''));
        if (str_starts_with($cleanPhone, '+63')) {
            $cleanPhone = '0' . substr($cleanPhone, 3);
        } elseif (str_starts_with($cleanPhone, '63') && strlen($cleanPhone) === 12) {
            $cleanPhone = '0' . substr($cleanPhone, 2);
        } elseif (preg_match('/^9\d{9}$/', $cleanPhone)) {
            $cleanPhone = '0' . $cleanPhone;
        }

        $rawTin = trim((string) $request->input('tin', ''));
        // Normalize TIN to XXX-XXX-XXX-XXX if numbers only
        $digitsOnlyTin = preg_replace('/[^0-9]/', '', $rawTin);
        if (strlen($digitsOnlyTin) >= 9 && strlen($digitsOnlyTin) <= 12 && !str_contains($rawTin, '-')) {
            $cleanTin = implode('-', str_split(str_pad($digitsOnlyTin, 12, '0', STR_PAD_RIGHT), 3));
        } else {
            $cleanTin = $rawTin;
        }

        $cleanCompanyName = trim((string) $request->input('company_name', ''));
        $cleanSecNo = trim((string) $request->input('sec_registration_no', ''));
        $cleanFirstName = trim((string) $request->input('contact_person_first_name', ''));
        $cleanLastName = trim((string) $request->input('contact_person_last_name', ''));

        $inputData = array_merge($request->all(), [
            'email' => $cleanEmail,
            'mobile_number' => $cleanPhone,
            'tin' => $cleanTin,
            'company_name' => $cleanCompanyName,
            'sec_registration_no' => $cleanSecNo,
            'contact_person_first_name' => $cleanFirstName,
            'contact_person_last_name' => $cleanLastName,
        ]);

        // 2. Strict Validation (TC-LOG-001 to TC-LOG-031)
        $nameRegex = '/^[\pL\s\-\'\.ñÑ]+$/u';
        $tinRegex = '/^\d{3}-\d{3}-\d{3}-\d{3}$/';

        $validator = Validator::make($inputData, [
            // Company Details
            'company_name' => ['required', 'string', 'min:3', 'max:255', 'unique:logistics_profiles,company_name'],
            'sec_registration_no' => ['required', 'string', 'min:5', 'max:100', 'unique:logistics_profiles,sec_registration_no'],
            'tin' => ['required', 'string', "regex:{$tinRegex}", 'unique:logistics_profiles,tin'],

            // Address Details
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'city_municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'min:5', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/^\d{4}$/'],

            // Contact Person Details
            'contact_person_first_name' => ['required', 'string', 'min:2', 'max:50', "regex:{$nameRegex}"],
            'contact_person_last_name' => ['required', 'string', 'min:2', 'max:50', "regex:{$nameRegex}"],
            'position' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email', 'unique:logistics_profiles,email'],
            'mobile_number' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,contact_number', 'unique:logistics_profiles,mobile_number'],

            // Service Areas (Multiple Selection)
            'service_areas' => ['required', 'array', 'min:1'],
            'service_areas.*' => ['required', 'string', 'max:100'],

            // Compliance Document Uploads (2MB limit per config)
            'sec_certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'bir_2303' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'mayors_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'valid_id' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],

            // Account Password & Terms
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
            'terms' => ['accepted'],
        ], [
            'tin.regex' => 'The Taxpayer Identification Number (TIN) must be formatted as XXX-XXX-XXX-XXX.',
            'mobile_number.regex' => 'The mobile number must be a valid 11-digit Philippine mobile number starting with 09 (e.g., 09171234567).',
            'contact_person_first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes.',
            'contact_person_last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes.',
            'postal_code.regex' => 'Postal code must be a valid 4-digit numeric code.',
            'service_areas.required' => 'Please select at least one regional service area of logistics coverage.',
            'sec_certificate.max' => 'The SEC Registration Certificate may not be greater than 2MB.',
            'bir_2303.max' => 'The BIR Certificate of Registration (Form 2303) may not be greater than 2MB.',
            'mayors_permit.max' => 'The Mayor\'s Business Permit may not be greater than 2MB.',
            'valid_id.max' => 'The Government-Issued ID may not be greater than 2MB.',
            'terms.accepted' => 'You must agree to the Nexora Market Terms of Service and Logistics Partner Service Agreement.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // 3. Store documents securely
        $secPath = $request->file('sec_certificate')->store('documents/logistics/sec', 'public');
        $birPath = $request->file('bir_2303')->store('documents/logistics/bir', 'public');
        $permitPath = $request->file('mayors_permit')->store('documents/logistics/permit', 'public');
        $idPath = $request->file('valid_id')->store('documents/logistics/id', 'public');

        // 4. Create User & LogisticsProfile inside a Database Transaction
        DB::beginTransaction();
        try {
            $user = User::create([
                'first_name' => $validated['contact_person_first_name'],
                'last_name' => $validated['contact_person_last_name'],
                'email' => $cleanEmail,
                'contact_number' => $cleanPhone,
                'password' => Hash::make($validated['password']),
                'role' => 'sorting_center',
                'approval_status' => 'pending',
                'account_status' => 'active',
                'valid_id_path' => $idPath,
                'email_verified_at' => null,
                'sex' => 'other',
                'birthday' => '2000-01-01',
                'age' => 26,
            ]);

            $profile = LogisticsProfile::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'sec_registration_no' => $validated['sec_registration_no'],
                'tin' => $cleanTin,
                'region' => $validated['region'],
                'province' => $validated['province'],
                'city_municipality' => $validated['city_municipality'],
                'barangay' => $validated['barangay'],
                'street_address' => $validated['street_address'],
                'postal_code' => $validated['postal_code'],
                'contact_person_first_name' => $validated['contact_person_first_name'],
                'contact_person_last_name' => $validated['contact_person_last_name'],
                'position' => $validated['position'],
                'email' => $cleanEmail,
                'mobile_number' => $cleanPhone,
                'service_areas' => $validated['service_areas'],
                'sec_certificate_path' => $secPath,
                'bir_2303_path' => $birPath,
                'mayors_permit_path' => $permitPath,
                'valid_id_path' => $idPath,
                'status' => 'unverified',
            ]);

            // Backwards compatibility profile
            SortingCenterProfile::create([
                'user_id' => $user->id,
                'business_name' => $validated['company_name'],
                'business_permit_path' => $permitPath,
            ]);

            // 5. Generate secure 6-digit OTP code (TC-LOG-032)
            $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Otp::create([
                'email' => $cleanEmail,
                'logistics_profile_id' => $profile->id,
                'otp_code' => $otpCode,
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ]);

            DB::commit();

            // 6. Send OTP Email Notification
            try {
                $user->notify(new LogisticsOtpNotification(
                    otp: $otpCode,
                    companyName: $profile->company_name,
                    contactName: $profile->contact_person_full_name
                ));
            } catch (\Throwable $e) {
                Log::error("Failed to send Logistics Partner OTP email: {$e->getMessage()}");
            }

            // Log user in and put email in session
            Auth::login($user);
            session(['logistics_verify_email' => $cleanEmail]);

            return redirect()->route('logistics.otp.notice')
                ->with('status', 'Registration successful! A 6-digit verification code has been dispatched to your email.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Logistics partner registration failed: {$e->getMessage()}");

            return back()->withInput()->withErrors([
                'company_name' => 'Failed to process registration: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Show OTP verification screen.
     */
    public function showOtpForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $email = $user?->email ?? session('logistics_verify_email');

        if (! $email) {
            return redirect()->route('logistics.register');
        }

        $profile = LogisticsProfile::where('email', $email)->first();

        if ($profile && $profile->status !== 'unverified') {
            return redirect()->route('logistics.pending');
        }

        return view('logistics.auth.verify-otp', [
            'email' => $email,
            'profile' => $profile,
        ]);
    }

    /**
     * Verify the 6-digit OTP code (TC-LOG-033 to TC-LOG-035).
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.size' => 'The verification code must be exactly 6 digits.',
            'otp.regex' => 'The verification code must consist of numbers only.',
        ]);

        $user = $request->user();
        $email = $user?->email ?? session('logistics_verify_email');

        if (! $email) {
            return redirect()->route('logistics.register');
        }

        $profile = LogisticsProfile::where('email', $email)->first();

        // Retrieve latest OTP record
        $otpRecord = Otp::where('email', $email)
            ->latest()
            ->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'otp' => 'No active verification code found. Please request a new code.',
            ]);
        }

        // Brute force protection check: max 5 attempts (TC-LOG-033)
        if ($otpRecord->hasExceededMaxAttempts(5)) {
            throw ValidationException::withMessages([
                'otp' => 'Maximum verification attempts exceeded (5 attempts). For your security, this code has been locked. Please request a new code.',
            ]);
        }

        // Expiration check: 10 minutes validity (TC-LOG-035)
        if ($otpRecord->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => 'The verification code has expired. Please click "Resend Code" to receive a new code.',
            ]);
        }

        // Code match check (TC-LOG-034)
        if (! $otpRecord->matches($request->input('otp'))) {
            $otpRecord->incrementAttempts();
            $remaining = max(0, 5 - $otpRecord->attempts);

            throw ValidationException::withMessages([
                'otp' => "The verification code you entered is invalid. ({$remaining} attempt(s) remaining).",
            ]);
        }

        // Valid code! Clean up and advance status (TC-LOG-037)
        $otpRecord->delete();

        if ($profile) {
            $profile->update([
                'status' => 'pending_approval',
            ]);
        }

        if ($user) {
            $user->forceFill([
                'email_verified_at' => now(),
                'approval_status' => 'pending',
            ])->save();
        }

        return redirect()->route('logistics.pending')
            ->with('status', 'Email verified successfully! Your application has been submitted to the platform administration for compliance review.');
    }

    /**
     * Resend a fresh 6-digit OTP code (TC-LOG-036).
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $email = $user?->email ?? session('logistics_verify_email');

        if (! $email) {
            return redirect()->route('logistics.register');
        }

        $profile = LogisticsProfile::where('email', $email)->first();

        // Invalidate old OTPs and generate a fresh one
        Otp::where('email', $email)->delete();

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Otp::create([
            'email' => $email,
            'logistics_profile_id' => $profile?->id,
            'otp_code' => $otpCode,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        try {
            $notifiable = $user ?? (object) [
                'email' => $email,
                'first_name' => $profile?->contact_person_first_name ?? 'Partner',
            ];

            if ($user) {
                $user->notify(new LogisticsOtpNotification(
                    otp: $otpCode,
                    companyName: $profile?->company_name ?? 'Logistics Partner',
                    contactName: $profile?->contact_person_full_name ?? 'Partner'
                ));
            } else {
                \Illuminate\Support\Facades\Notification::route('mail', $email)
                    ->notify(new LogisticsOtpNotification(
                        otp: $otpCode,
                        companyName: $profile?->company_name ?? 'Logistics Partner',
                        contactName: $profile?->contact_person_full_name ?? 'Partner'
                    ));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to resend Logistics OTP email: {$e->getMessage()}");
        }

        return back()->with('status', 'otp-sent');
    }

    /**
     * Pending Approval Screen (TC-LOG-037, TC-LOG-038).
     */
    public function pending(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $profile = $user?->logisticsProfile;

        if ($profile?->isRejected() || $user?->isDisapproved()) {
            return redirect()->route('logistics.resubmit');
        }

        if ($profile?->isApproved() && $user?->isApproved()) {
            return redirect()->route('logistics.dashboard');
        }

        return view('logistics.auth.pending', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    /**
     * Show Resubmission Form after Rejection (TC-LOG-041, TC-LOG-042).
     */
    public function showResubmitForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $profile = $user?->logisticsProfile;

        if (! $profile || (! $profile->isRejected() && ! $user->isDisapproved())) {
            return redirect()->route('logistics.dashboard');
        }

        return view('logistics.auth.resubmit', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    /**
     * Process Application Resubmission (TC-LOG-042).
     */
    public function resubmit(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user?->logisticsProfile;

        if (! $profile) {
            return redirect()->route('logistics.register');
        }

        $nameRegex = '/^[\pL\s\-\'\.ñÑ]+$/u';
        $tinRegex = '/^\d{3}-\d{3}-\d{3}-\d{3}$/';

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:3', 'max:255', 'unique:logistics_profiles,company_name,' . $profile->id],
            'sec_registration_no' => ['required', 'string', 'min:5', 'max:100', 'unique:logistics_profiles,sec_registration_no,' . $profile->id],
            'tin' => ['required', 'string', "regex:{$tinRegex}", 'unique:logistics_profiles,tin,' . $profile->id],
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'city_municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'min:5', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/^\d{4}$/'],
            'contact_person_first_name' => ['required', 'string', 'min:2', 'max:50', "regex:{$nameRegex}"],
            'contact_person_last_name' => ['required', 'string', 'min:2', 'max:50', "regex:{$nameRegex}"],
            'position' => ['required', 'string', 'min:2', 'max:100'],
            'mobile_number' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:logistics_profiles,mobile_number,' . $profile->id],
            'service_areas' => ['required', 'array', 'min:1'],
            'service_areas.*' => ['required', 'string', 'max:100'],

            // Documents are nullable on resubmit (keep existing if not re-uploaded)
            'sec_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'bir_2303' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'mayors_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'valid_id' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('sec_certificate')) {
            $profile->sec_certificate_path = $request->file('sec_certificate')->store('documents/logistics/sec', 'public');
        }
        if ($request->hasFile('bir_2303')) {
            $profile->bir_2303_path = $request->file('bir_2303')->store('documents/logistics/bir', 'public');
        }
        if ($request->hasFile('mayors_permit')) {
            $profile->mayors_permit_path = $request->file('mayors_permit')->store('documents/logistics/permit', 'public');
        }
        if ($request->hasFile('valid_id')) {
            $profile->valid_id_path = $request->file('valid_id')->store('documents/logistics/id', 'public');
            $user->valid_id_path = $profile->valid_id_path;
        }

        $profile->fill([
            'company_name' => $validated['company_name'],
            'sec_registration_no' => $validated['sec_registration_no'],
            'tin' => $validated['tin'],
            'region' => $validated['region'],
            'province' => $validated['province'],
            'city_municipality' => $validated['city_municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'postal_code' => $validated['postal_code'],
            'contact_person_first_name' => $validated['contact_person_first_name'],
            'contact_person_last_name' => $validated['contact_person_last_name'],
            'position' => $validated['position'],
            'mobile_number' => $validated['mobile_number'],
            'service_areas' => $validated['service_areas'],
            'status' => 'pending_approval',
            'rejection_reason' => null,
        ])->save();

        $user->forceFill([
            'first_name' => $validated['contact_person_first_name'],
            'last_name' => $validated['contact_person_last_name'],
            'contact_number' => $validated['mobile_number'],
            'approval_status' => 'pending',
            'disapproval_reason' => null,
        ])->save();

        return redirect()->route('logistics.pending')
            ->with('status', 'Your application has been updated and resubmitted for admin compliance review.');
    }
}

