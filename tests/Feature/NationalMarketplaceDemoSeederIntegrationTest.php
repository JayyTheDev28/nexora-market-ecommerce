<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\NationalMarketplaceDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NationalMarketplaceDemoSeederIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_demo_seed_package_assets_manifest_and_schema_prerequisites_are_valid(): void
    {
        $this->assertTrue(class_exists(NationalMarketplaceDemoSeeder::class));

        $manifestPath = database_path('seeders/demo_product_images.json');
        $this->assertFileExists($manifestPath);

        $catalog = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($catalog);
        $this->assertGreaterThanOrEqual(50, count($catalog));

        $disk = Storage::disk('public');
        foreach ($catalog as $entry) {
            $file = 'seed_products/' . $entry['image'];
            $this->assertTrue($disk->exists($file), "Missing public disk asset: {$file}");
            $this->assertFileExists(public_path('storage/' . $file), "Missing symlinked public asset: {$file}");
        }

        // Verify addresses table supports nullable barangay fields when non-hub cities omit barangays
        $this->assertTrue(Schema::hasColumns('addresses', ['barangay_code', 'barangay_name', 'needs_manual_review', 'manual_review_reason']));

        $buyer = User::create([
            'first_name' => 'SeedCheck',
            'last_name' => 'Buyer',
            'sex' => 'male',
            'birthday' => '1995-01-01',
            'age' => 31,
            'contact_number' => '09170009999',
            'email' => 'seedcheck_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $address = Address::create([
            'user_id' => $buyer->id,
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '041000000',
            'province_name' => 'Batangas',
            'municipality_code' => '041001000',
            'municipality_name' => 'Agoncillo',
            'barangay_code' => null,
            'barangay_name' => null,
            'street' => 'Demo Market Street',
            'house_number' => 'Block 1',
            'recipient_name' => 'SeedCheck Buyer',
            'recipient_phone' => '09170009999',
            'is_default' => true,
            'needs_manual_review' => true,
            'manual_review_reason' => 'Barangay not present in local PSGC reference.',
        ]);

        $this->assertNull($address->barangay_code);
        $this->assertTrue($address->needs_manual_review);
        $this->assertStringContainsString('Batangas', $address->full_address);
    }
}
