<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SellerSeeder::class,
            ProductSeeder::class,
            LogisticsSeeder::class,
            AdminUserSeeder::class,
            VoucherSeeder::class,
            BuyerSeeder::class,
        ]);
    }
}
