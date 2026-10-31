<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    /**
     * B7: Browse available vouchers and user's claimed vouchers
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // All active platform & store vouchers that have not expired
        $availableVouchers = Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('seller.sellerProfile')
            ->get();

        $claimedIds = $user->claimedVouchers()->pluck('voucher_id')->all();

        return view('buyer.vouchers.index', compact('availableVouchers', 'claimedIds'));
    }

    /**
     * B7: Claim a voucher
     */
    public function claim(Request $request, Voucher $voucher): JsonResponse
    {
        $user = $request->user();

        if (! $voucher->is_active || $voucher->isExpired()) {
            return response()->json(['error' => 'This voucher is no longer active or has expired.'], 422);
        }

        $existing = UserVoucher::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already claimed this voucher.'], 200);
        }

        UserVoucher::create([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
            'claimed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Voucher {$voucher->code} claimed successfully!",
        ]);
    }
}
