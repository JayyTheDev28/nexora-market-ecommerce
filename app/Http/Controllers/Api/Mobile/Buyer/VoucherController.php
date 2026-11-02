<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\VoucherResource;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * B7: Available vouchers and claimed vouchers
     * GET /api/mobile/v1/buyer/vouchers
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $availableVouchers = Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('seller.sellerProfile')
            ->get();

        $userVouchers = $user->vouchers()
            ->with('seller.sellerProfile')
            ->get();

        return response()->json([
            'data' => [
                'available_vouchers' => VoucherResource::collection($availableVouchers),
                'claimed_vouchers' => VoucherResource::collection($userVouchers),
            ],
            'message' => 'Vouchers retrieved successfully.',
        ]);
    }

    /**
     * B7: Claim a voucher
     * POST /api/mobile/v1/buyer/vouchers/{voucher}/claim
     */
    public function claim(Request $request, Voucher $voucher): JsonResponse
    {
        $user = $request->user();

        if (! $voucher->is_active || $voucher->isExpired()) {
            return response()->json([
                'message' => 'This voucher is no longer active or has expired.',
                'errors' => ['voucher' => ['Voucher expired or inactive.']],
            ], 422);
        }

        $existing = UserVoucher::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => new VoucherResource($voucher),
                'message' => 'You have already claimed this voucher.',
            ], 200);
        }

        UserVoucher::create([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
            'claimed_at' => now(),
        ]);

        return response()->json([
            'data' => new VoucherResource($voucher),
            'message' => "Voucher {$voucher->code} claimed successfully!",
        ], 200);
    }
}

