<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\SampleCatalog;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (SampleCatalog::categories() as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'icon' => $category['icon']]
            );
        }
    }
}
