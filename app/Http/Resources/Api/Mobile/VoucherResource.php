<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isClaimed = false;
        $isUsed = false;

        if ($user) {
            $userVoucher = $user->claimedVouchers()->where('voucher_id', $this->id)->first();
            $isClaimed = $userVoucher !== null;
            $isUsed = $userVoucher?->pivot?->used_at !== null;
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title ?? $this->code,
            'voucher_scope' => $this->voucher_scope,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'min_spend' => (float) ($this->min_spend ?? 0),
            'max_discount' => $this->max_discount ? (float) $this->max_discount : null,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'seller_id' => $this->seller_id,
            'seller_name' => $this->seller?->sellerProfile?->business_name ?? $this->seller?->full_name,
            'is_claimed' => $isClaimed,
            'is_used' => $isUsed,
        ];
    }
}

