<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $techHaven = User::where('email', 'techhaven@nexora.test')->first();
        $nexusHome = User::where('email', 'nexushome@nexora.test')->first();

        $vouchers = [
            [
                'code' => 'NEXORA100',
                'seller_id' => null,
                'voucher_scope' => 'platform',
                'type' => 'flat',
                'value' => 100.00,
                'min_spend' => 500.00,
                'max_discount' => 100.00,
                'usage_limit' => 500,
                'is_active' => true,
                'expires_at' => now()->addMonths(3),
            ],
            [
                'code' => 'MEGA20',
                'seller_id' => null,
                'voucher_scope' => 'platform',
                'type' => 'percent',
                'value' => 20.00,
                'min_spend' => 1000.00,
                'max_discount' => 300.00,
                'usage_limit' => 200,
                'is_active' => true,
                'expires_at' => now()->addMonths(2),
            ],
            [
                'code' => 'FREESHIP',
                'seller_id' => null,
                'voucher_scope' => 'shipping',
                'type' => 'flat',
                'value' => 50.00,
                'min_spend' => 300.00,
                'max_discount' => 50.00,
                'usage_limit' => 1000,
                'is_active' => true,
                'expires_at' => now()->addMonths(6),
            ],
        ];

        if ($techHaven) {
            $vouchers[] = [
                'code' => 'TECH10',
                'seller_id' => $techHaven->id,
                'voucher_scope' => 'store',
                'type' => 'percent',
                'value' => 10.00,
                'min_spend' => 800.00,
                'max_discount' => 250.00,
                'usage_limit' => 100,
                'is_active' => true,
                'expires_at' => now()->addMonths(2),
            ];
        }

        if ($nexusHome) {
            $vouchers[] = [
                'code' => 'HOME50',
                'seller_id' => $nexusHome->id,
                'voucher_scope' => 'store',
                'type' => 'flat',
                'value' => 50.00,
                'min_spend' => 400.00,
                'max_discount' => 50.00,
                'usage_limit' => 100,
                'is_active' => true,
                'expires_at' => now()->addMonths(2),
            ];
        }

        foreach ($vouchers as $voucher) {
            Voucher::updateOrCreate(
                ['code' => $voucher['code']],
                $voucher
            );
        }
    }
}
