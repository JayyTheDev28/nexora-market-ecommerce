<?php

namespace App\Http\Resources\Api\Mobile;

use App\Models\StoreFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $profile = $this->sellerProfile;

        $isFollowing = false;
        if ($user) {
            $isFollowing = StoreFollow::where('user_id', $user->id)->where('seller_id', $this->id)->exists();
        }

        return [
            'id' => $this->id,
            'name' => $profile?->business_name ?? $this->full_name,
            'category' => $profile?->category ?? 'Retail & General Merchandise',
            'avatar_url' => $this->avatar_url,
            'banner_url' => $profile?->banner_path ? asset('storage/' . $profile->banner_path) : null,
            'description' => $profile?->description,
            'is_vacation_mode' => (bool) ($profile?->is_vacation_mode ?? false),
            'followers_count' => StoreFollow::where('seller_id', $this->id)->count(),
            'products_count' => $this->products()->where('status', 'active')->count(),
            'is_following' => $isFollowing,
            'categories' => $this->storeCategories()
                ->where('is_visible', true)
                ->whereNull('parent_id')
                ->orderBy('order_index')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ]),
        ];
    }
}

