<?php

namespace Tests\Feature;

use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\TransferManifest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedRiderAndTransferManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function createLogisticsCompany(string $name, string $email): User
    {
        return User::create([
            'first_name' => $name,
            'last_name' => 'Logistics',
            'email' => $email,
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'birthday' => '1990-01-01',
            'age' => 35,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function createHub(User $company, string $name, string $code): Hub
    {
        return Hub::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043414000',
            'city_name' => 'Liliw',
            'barangay_code' => '043414001',
            'barangay_name' => 'Bagong Anyo',
            'is_active' => true,
        ]);
    }

    protected function createPersonnel(User $company, string $role, string $email, string $status = 'active'): User
    {
        $user = User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Test',
            'email' => $email,
            'contact_number' => '0918' . random_int(1000000, 9999999),
            'birthday' => '1992-01-01',
            'age' => 33,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => $status,
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $user->id,
            'sorting_center_id' => $company->id,
            'logistics_company_id' => $company->id,
            'vehicle_type' => $role === 'rider' ? 'Motorcycle' : null,
            'plate_number' => $role === 'rider' ? 'MC-' . random_int(1000, 9999) : null,
            'license_number' => 'N01-23-' . random_int(100000, 999999),
            'license_expiry_date' => now()->addYear(),
        ]);

        return $user;
    }

    protected function createOrderAtHub(User $company, Hub $originHub, Hub $destHub, string $tracking): Order
    {
        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'One',
            'email' => 'buyer_' . uniqid() . '@example.com',
            'contact_number' => '0919' . random_int(1000000, 9999999),
            'birthday' => '1995-01-01',
            'age' => 30,
            'sex' => 'female',
            'password' => Hash::make('Password123!'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'One',
            'email' => 'seller_' . uniqid() . '@example.com',
            'contact_number' => '0920' . random_int(1000000, 9999999),
            'birthday' => '1990-01-01',
            'age' => 35,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $address = \App\Models\Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043414000',
            'municipality_name' => 'Liliw',
            'barangay_code' => '043414001',
            'barangay_name' => 'Bagong Anyo',
            'street' => '123 Main St',
            'recipient_name' => 'Buyer One',
            'recipient_phone' => '09191112222',
            'is_default' => true,
        ]);

        return Order::create([
            'order_number' => 'NX-' . strtoupper(uniqid()),
            'tracking_number' => $tracking,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'subtotal' => 500,
            'shipping_fee' => 50,
            'total' => 550,
            'payment_method' => 'cod',
            'status' => 'received_at_hub',
            'pickup_logistics_id' => $company->id,
            'sorting_center_id' => $company->id,
            'current_custody_logistics_id' => $company->id,
            'origin_hub_id' => $originHub->id,
            'current_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'shipping_name' => 'Buyer One',
            'shipping_phone' => '09191112222',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Liliw',
            'shipping_province' => 'Laguna',
        ]);
    }

    public function test_unified_login_returns_distinct_roles_and_enforces_api_boundaries(): void
    {
        $company = $this->createLogisticsCompany('Alpha', 'alpha@logistics.test');
        $rider = $this->createPersonnel($company, 'rider', 'rider@test.com');
        $courier = $this->createPersonnel($company, 'courier', 'courier@test.com');

        // 1. Rider logs in and gets role='rider'
        $riderLogin = $this->postJson('/api/mobile/v1/auth/rider/login', [
            'email' => $rider->email,
            'password' => 'Password123!',
        ]);
        $riderLogin->assertOk()->assertJsonPath('data.role', 'rider');

        // 2. Transfer Courier logs in through same endpoint and gets role='courier'
        $courierLogin = $this->postJson('/api/mobile/v1/auth/rider/login', [
            'email' => $courier->email,
            'password' => 'Password123!',
        ]);
        $courierLogin->assertOk()->assertJsonPath('data.role', 'courier');

        // 3. Rider cannot access transfer-courier APIs
        Sanctum::actingAs($rider);
        $this->getJson('/api/mobile/v1/transfer-courier/dashboard')
            ->assertStatus(403);

        // 4. Transfer Courier cannot access rider COD, earnings, or delivery APIs
        Sanctum::actingAs($courier);
        $this->getJson('/api/mobile/v1/rider/earnings')
            ->assertStatus(403);
        $this->getJson('/api/mobile/v1/rider/deliveries/active')
            ->assertStatus(403);

        // 18. Existing Rider API remains operational
        Sanctum::actingAs($rider);
        $this->getJson('/api/mobile/v1/rider/profile')
            ->assertOk();
    }

    public function test_logistics_creates_and_activates_transfer_courier_without_vehicle_registration(): void
    {
        Storage::fake('public');
        $company = $this->createLogisticsCompany('Beta', 'beta@logistics.test');
        $hub = $this->createHub($company, 'Beta Hub', 'BETA-1');

        $response = $this->actingAs($company)->post('/logistics/couriers', [
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
            'birthday' => '1994-05-10',
            'email' => 'carlos.courier@test.com',
            'contact_number' => '09171234567',
            'license_number' => 'N01-23-456789',
            'license_expiry_date' => now()->addYears(2)->format('Y-m-d'),
            'license_file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            'assigned_hub_id' => $hub->id,
        ]);

        $response->assertRedirect();

        $courier = User::where('email', 'carlos.courier@test.com')->firstOrFail();
        $this->assertSame('courier', $courier->role);
        $this->assertSame('pending_activation', $courier->account_status);
        $this->assertNull($courier->courierProfile->plate_number);
        $this->assertSame($hub->id, $courier->courierProfile->assigned_hub_id);

        // Activate via mobile activation endpoint
        $this->postJson("/api/mobile/v1/auth/rider/activate/{$courier->activation_token}", [
            'password' => 'CourierPass@2026',
            'password_confirmation' => 'CourierPass@2026',
        ])->assertOk();

        $courier->refresh();
        $this->assertSame('active', $courier->account_status);
    }

    public function test_full_inter_company_manifest_lifecycle_loading_departure_arrival_and_partial_receipt(): void
    {
        $senderCompany = $this->createLogisticsCompany('Sender', 'sender@logistics.test');
        $receiverCompany = $this->createLogisticsCompany('Receiver', 'receiver@logistics.test');
        $originHub = $this->createHub($senderCompany, 'Origin Hub', 'ORG-1');
        $destHub = $this->createHub($receiverCompany, 'Dest Hub', 'DST-1');

        $courier = $this->createPersonnel($senderCompany, 'courier', 'driver1@sender.test');
        $otherCourier = $this->createPersonnel($senderCompany, 'courier', 'driver2@sender.test');
        $suspendedCourier = $this->createPersonnel($senderCompany, 'courier', 'suspended@sender.test', 'suspended');
        $rider = $this->createPersonnel($senderCompany, 'rider', 'rider1@sender.test');

        $order1 = $this->createOrderAtHub($senderCompany, $originHub, $destHub, 'TRK-0001');
        $order2 = $this->createOrderAtHub($senderCompany, $originHub, $destHub, 'TRK-0002');

        // 8. Unauthorized/suspended/rider cannot be assigned
        $this->actingAs($senderCompany)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => [$order1->id, $order2->id],
            'assigned_courier_id' => $suspendedCourier->id,
        ])->assertStatus(422);

        $this->actingAs($senderCompany)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => [$order1->id, $order2->id],
            'assigned_courier_id' => $rider->id,
        ])->assertStatus(422);

        // 6 & 7. Create multi-parcel manifest and assign authorized courier
        $this->actingAs($senderCompany)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => [$order1->id, $order2->id],
            'assigned_courier_id' => $courier->id,
            'vehicle_plate_number' => 'NXA 9988',
        ])->assertRedirect();

        $manifest = TransferManifest::firstOrFail();
        $this->assertSame('loading', $manifest->status);
        $this->assertSame(2, $manifest->total_parcels);
        // Parcels must NOT be marked in_transit_hub on draft/loading creation
        $this->assertSame('received_at_hub', $order1->fresh()->status);

        // 9. Courier sees only assigned manifests
        Sanctum::actingAs($otherCourier);
        $this->getJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}")
            ->assertStatus(403);

        // 10 & 11. Scan parcels + duplicate scan idempotency
        Sanctum::actingAs($courier);
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
                'tracking_number' => 'TRK-0001',
            ])
            ->assertOk()
            ->assertJsonPath('data.loaded_count', 1);

        // Duplicate scan does not increase count
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
                'tracking_number' => 'TRK-0001',
            ])
            ->assertOk()
            ->assertJsonPath('data.loaded_count', 1);

        // Unrelated parcel cannot be scanned
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
                'tracking_number' => 'TRK-UNRELATED',
            ])
            ->assertStatus(422);

        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
                'tracking_number' => 'TRK-0002',
            ])
            ->assertOk()
            ->assertJsonPath('data.loaded_count', 2)
            ->assertJsonPath('data.manifest.status', 'ready_to_dispatch');

        // 12. Courier confirms departure
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/depart")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        $this->assertSame('in_transit_hub', $order1->fresh()->status);
        // Custody stays with sender during transit!
        $this->assertSame($senderCompany->id, $order1->fresh()->current_custody_logistics_id);

        // 13. Courier reports arrival (does NOT complete transfer or transfer custody)
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/arrive")
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived');
        $this->assertSame($senderCompany->id, $order1->fresh()->current_custody_logistics_id);

        // 14, 15, 16. Receiving operator accepts TRK-0001 and marks TRK-0002 missing
        $this->actingAs($receiverCompany)->post("/logistics/transfers/manifests/{$manifest->id}/verify-parcel", [
            'tracking_number' => 'TRK-0001',
            'classification' => 'received',
        ])->assertRedirect();

        $this->actingAs($receiverCompany)->post("/logistics/transfers/manifests/{$manifest->id}/verify-parcel", [
            'tracking_number' => 'TRK-0002',
            'classification' => 'missing',
            'notes' => 'Not in truck upon unloading',
        ])->assertRedirect();

        $manifest->refresh();
        $this->assertSame('exception', $manifest->status);
        // Custody transferred ONLY for TRK-0001, NOT TRK-0002
        $this->assertSame($receiverCompany->id, $order1->fresh()->current_custody_logistics_id);
        $this->assertSame($senderCompany->id, $order2->fresh()->current_custody_logistics_id);

        // Resolve TRK-0002 as received -> manifest becomes completed
        $this->actingAs($receiverCompany)->post("/logistics/transfers/manifests/{$manifest->id}/verify-parcel", [
            'tracking_number' => 'TRK-0002',
            'classification' => 'received',
        ])->assertRedirect();

        $this->assertSame('completed', $manifest->fresh()->status);
        $this->assertSame($receiverCompany->id, $order2->fresh()->current_custody_logistics_id);

        // 17. Completed manifest cannot be processed again
        $this->actingAs($receiverCompany)->post("/logistics/transfers/manifests/{$manifest->id}/verify-parcel", [
            'tracking_number' => 'TRK-0001',
            'classification' => 'missing',
        ])->assertStatus(422);
    }

    public function test_internal_manifest_and_historical_single_parcel_transfer_work(): void
    {
        $company = $this->createLogisticsCompany('Gamma', 'gamma@logistics.test');
        $hubA = $this->createHub($company, 'Hub A', 'GAM-A');
        $hubB = $this->createHub($company, 'Hub B', 'GAM-B');
        $courier = $this->createPersonnel($company, 'courier', 'driver@gamma.test');

        $orderManifest = $this->createOrderAtHub($company, $hubA, $hubB, 'TRK-INT-1');
        $orderLegacy = $this->createOrderAtHub($company, $hubA, $hubB, 'TRK-LEG-1');

        // 19. Internal manifest
        $this->actingAs($company)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'order_ids' => [$orderManifest->id],
            'assigned_courier_id' => $courier->id,
        ])->assertRedirect();

        $manifest = TransferManifest::firstOrFail();
        $this->assertTrue($manifest->is_internal);

        // 20. Historical single-parcel dispatch & receipt remains operational
        $this->actingAs($company)->post("/logistics/transfers/{$orderLegacy->id}/dispatch", [
            'sender_hub_id' => $hubA->id,
            'receiver_hub_id' => $hubB->id,
        ])->assertRedirect();

        $transfer = HubTransfer::where('order_id', $orderLegacy->id)->firstOrFail();
        $this->assertSame('in_transit', $transfer->status);

        $this->actingAs($company)->post("/logistics/transfers/{$transfer->id}/confirm-receipt")
            ->assertRedirect();
        $this->assertSame('received', $transfer->fresh()->status);
    }
}
