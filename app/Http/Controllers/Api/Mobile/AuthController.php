<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\BuyerLoginRequest;
use App\Http\Requests\Api\Mobile\BuyerRegisterRequest;
use App\Http\Requests\Api\Mobile\RiderActivateRequest;
use App\Http\Requests\Api\Mobile\RiderLoginRequest;
use App\Http\Requests\Api\Mobile\VerifyEmailOtpRequest;
use App\Http\Resources\Api\Mobile\RiderProfileResource;
use App\Http\Resources\Api\Mobile\UserResource;
use App\Models\Address;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Buyer token login
     * POST /api/mobile/v1/auth/buyer/login
     */
    public function buyerLogin(BuyerLoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => "Those credentials don't match our records.",
                'errors' => [
                    'email' => ["Those credentials don't match our records."],
                ],
            ], 401);
        }

        if (! $user->isBuyer()) {
            return response()->json([
                'message' => 'Access denied. Account is not a registered buyer.',
                'errors' => [
                    'role' => ['Access denied. Account is not a registered buyer.'],
                ],
            ], 403);
        }

        if ($user->isDisapproved()) {
            $reason = $user->disapproval_reason ? " Reason: {$user->disapproval_reason}" : '';
            return response()->json([
                'message' => "Your application was not approved.{$reason}",
                'errors' => [
                    'approval' => ["Your application was not approved.{$reason}"],
                ],
            ], 403);
        }

        if ($user->account_status === 'pending_activation') {
            return response()->json([
                'message' => 'Account Pending Activation. Please check your email for the activation link.',
                'errors' => [
                    'status' => ['Account Pending Activation.'],
                ],
            ], 403);
        }

        if (! $user->isActive()) {
            $label = $user->isSuspended() ? 'suspended' : 'deactivated';
            $reason = $user->status_reason ? " Reason: {$user->status_reason}" : '';
            return response()->json([
                'message' => "Your account has been {$label}.{$reason} Please contact support.",
                'errors' => [
                    'status' => ["Your account has been {$label}."],
                ],
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationOtp();
            return response()->json([
                'message' => 'Please verify your email address. A 6-digit verification code has been sent to your email.',
                'data' => [
                    'email_verified' => false,
                    'email' => $user->email,
                ],
                'errors' => [
                    'email' => ['Please verify your email first.'],
                ],
            ], 403);
        }

        if ($user->isPending()) {
            return response()->json([
                'message' => 'Your application is still pending approval.',
                'data' => [
                    'approval_status' => 'pending',
                ],
                'errors' => [
                    'approval' => ['Your application is still pending approval.'],
                ],
            ], 403);
        }

        $token = $user->createToken('buyer-app')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'message' => 'Login successful.',
        ]);
    }

    /**
     * Buyer registration
     * POST /api/mobile/v1/auth/buyer/register
     */
    public function buyerRegister(BuyerRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $age = Carbon::parse($validated['birthday'])->age;

        $validIdPath = $request->hasFile('valid_id')
            ? $request->file('valid_id')->store('ids', 'public')
            : null;

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'] ?? 'other',
            'birthday' => $validated['birthday'],
            'age' => $age,
            'email' => $validated['email'],
            'contact_number' => $validated['contact_number'],
            'password' => Hash::make($validated['password']),
            'role' => 'buyer',
            'approval_status' => 'pending',
            'valid_id_path' => $validIdPath,
        ]);

        if (! empty($validated['province_code']) && ! empty($validated['municipality_code']) && ! empty($validated['barangay_code'])) {
            Address::create([
                'user_id' => $user->id,
                'province_code' => $validated['province_code'],
                'province_name' => $validated['province_name'] ?? $validated['province_code'],
                'municipality_code' => $validated['municipality_code'],
                'municipality_name' => $validated['municipality_name'] ?? $validated['municipality_code'],
                'barangay_code' => $validated['barangay_code'],
                'barangay_name' => $validated['barangay_name'] ?? $validated['barangay_code'],
                'street' => $validated['street'] ?? null,
                'house_number' => $validated['house_number'] ?? null,
                'recipient_name' => $user->full_name,
                'recipient_phone' => $user->contact_number,
                'is_default' => true,
            ]);
        }

        event(new Registered($user));
        $user->sendEmailVerificationOtp(force: true);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'email_verification_required' => true,
            ],
            'message' => 'Registration successful. A 6-digit verification code has been sent to your email.',
        ], 201);
    }

    /**
     * Email OTP verification
     * POST /api/mobile/v1/auth/buyer/email/verify
     */
    public function verifyEmailOtp(VerifyEmailOtpRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user && $request->filled('email')) {
            $user = User::where('email', $request->email)->first();
        }

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
                'errors' => [
                    'email' => ['User not found.'],
                ],
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            $token = $user->createToken('buyer-app')->plainTextToken;
            return response()->json([
                'data' => [
                    'token' => $token,
                    'email_verified' => true,
                    'user' => new UserResource($user),
                ],
                'message' => 'Email is already verified.',
            ]);
        }

        if (! $user->email_verification_otp || $user->email_verification_otp !== $request->otp) {
            return response()->json([
                'message' => 'The verification code you entered is invalid.',
                'errors' => [
                    'otp' => ['The verification code you entered is invalid.'],
                ],
            ], 422);
        }

        if (! $user->email_verification_otp_expires_at || $user->email_verification_otp_expires_at->isPast()) {
            return response()->json([
                'message' => 'The verification code has expired. Please request a new code.',
                'errors' => [
                    'otp' => ['The verification code has expired. Please request a new code.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ])->save();

        $user->markEmailAsVerified();
        event(new Verified($user));

        $token = $user->createToken('buyer-app')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'email_verified' => true,
                'user' => new UserResource($user->fresh()),
            ],
            'message' => 'Email verified successfully.',
        ]);
    }

    /**
     * Resend email OTP
     * POST /api/mobile/v1/auth/buyer/email/resend
     */
    public function resendEmailOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user && $request->filled('email')) {
            $user = User::where('email', $request->input('email'))->first();
        }

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
                'errors' => [
                    'email' => ['User not found.'],
                ],
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 400);
        }

        $user->sendEmailVerificationOtp(force: true);

        return response()->json([
            'data' => null,
            'message' => 'A new 6-digit verification code has been sent to your email.',
        ]);
    }

    /**
     * Rider token login
     * POST /api/mobile/v1/auth/rider/login
     */
    public function riderLogin(RiderLoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
                'errors' => [
                    'email' => ['Invalid email or password.'],
                ],
            ], 401);
        }

        if (! $user->isRider()) {
            return response()->json([
                'message' => 'Access denied. Account is not a registered rider.',
                'errors' => [
                    'role' => ['Access denied. Account is not a registered rider.'],
                ],
            ], 403);
        }

        if ($user->isDisapproved()) {
            $reason = $user->disapproval_reason ? " Reason: {$user->disapproval_reason}" : '';
            return response()->json([
                'message' => "Your application was not approved.{$reason}",
                'errors' => [
                    'approval' => ["Your application was not approved.{$reason}"],
                ],
            ], 403);
        }

        if ($user->account_status === 'pending_activation') {
            return response()->json([
                'message' => 'Account Pending Activation. Please complete your password setup via your activation email.',
                'errors' => [
                    'status' => ['Account Pending Activation. Please complete your password setup via your activation email.'],
                ],
            ], 403);
        }

        if ($user->account_status !== 'active') {
            return response()->json([
                'message' => 'Account is suspended or deactivated. Contact your logistics operator.',
                'errors' => [
                    'status' => ['Account is suspended or deactivated. Contact your logistics operator.'],
                ],
            ], 403);
        }

        $token = $user->createToken('rider-app')->plainTextToken;
        $profile = $user->courierProfile()->with('sortingCenter')->first();

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
                'courier_profile' => new RiderProfileResource($profile),
            ],
            'message' => 'Login successful.',
        ]);
    }

    /**
     * Rider activation
     * POST /api/mobile/v1/auth/rider/activate/{token}
     */
    public function riderActivate(RiderActivateRequest $request, string $token): JsonResponse
    {
        $rider = User::whereIn('role', ['rider', 'courier'])
            ->where('activation_token', $token)
            ->first();

        if (! $rider) {
            return response()->json([
                'message' => 'This activation link is invalid or has already been used.',
                'errors' => [
                    'token' => ['This activation link is invalid or has already been used.'],
                ],
            ], 404);
        }

        if ($rider->activation_token_expires_at && $rider->activation_token_expires_at->isPast()) {
            return response()->json([
                'message' => 'This activation link has expired. Please contact your logistics administrator.',
                'errors' => [
                    'token' => ['This activation link has expired. Please contact your logistics administrator.'],
                ],
            ], 422);
        }

        $rider->update([
            'password' => Hash::make($request->password),
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
        ]);

        $authToken = $rider->createToken('rider-app')->plainTextToken;
        $profile = $rider->courierProfile()->with('sortingCenter')->first();

        return response()->json([
            'data' => [
                'token' => $authToken,
                'user' => new UserResource($rider->fresh()),
                'courier_profile' => new RiderProfileResource($profile),
            ],
            'message' => 'Rider account activated successfully.',
        ]);
    }

    /**
     * Authenticated user profile
     * GET /api/mobile/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user' => new UserResource($user),
        ];

        if ($user->isRider()) {
            $profile = $user->courierProfile()->with('sortingCenter')->first();
            $data['courier_profile'] = new RiderProfileResource($profile);
        }

        return response()->json([
            'data' => $data,
            'message' => 'Profile retrieved successfully.',
        ]);
    }

    /**
     * Token logout
     * POST /api/mobile/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'data' => null,
            'message' => 'Successfully logged out.',
        ]);
    }
}

