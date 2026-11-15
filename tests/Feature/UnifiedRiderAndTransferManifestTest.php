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

        // 20. Direct HTTP single-parcel dispatch bypass is blocked (422), while historical transfers remain receivable
        $this->actingAs($company)->post("/logistics/transfers/{$orderLegacy->id}/dispatch", [
            'sender_hub_id' => $hubA->id,
            'receiver_hub_id' => $hubB->id,
        ])->assertStatus(422);

        $transfer = HubTransfer::create([
            'order_id' => $orderLegacy->id,
            'tracking_number' => $orderLegacy->tracking_number,
            'sender_logistics_id' => $company->id,
            'sender_hub_id' => $hubA->id,
            'receiver_logistics_id' => $company->id,
            'receiver_hub_id' => $hubB->id,
            'status' => 'in_transit',
            'dispatched_at' => now()->subHour(),
            'dispatched_by' => $company->id,
        ]);
        $orderLegacy->update(['status' => 'in_transit_hub', 'current_hub_id' => null]);

        $this->actingAs($company)->get('/logistics/transfers?tab=incoming')->assertOk();

        $this->actingAs($company)->post("/logistics/transfers/{$transfer->id}/confirm-receipt")
            ->assertRedirect();
        $this->assertSame('received', $transfer->fresh()->status);
    }

    public function test_security_origin_validation_exception_arrival_and_custody_protections(): void
    {
        $company = $this->createLogisticsCompany('Delta', 'delta@logistics.test');
        $hubA = $this->createHub($company, 'Hub A', 'DEL-A');
        $hubB = $this->createHub($company, 'Hub B', 'DEL-B');
        $courier = $this->createPersonnel($company, 'courier', 'courier.delta@test.com');

        // 1. Transfer courier cannot access Rider-only web routes (redirects on web, 403 on JSON)
        $this->actingAs($courier)->get('/rider/dashboard')->assertRedirect();
        $this->actingAs($courier)->getJson('/rider/dashboard')->assertStatus(403);
        $this->actingAs($courier)->get('/courier/dashboard')->assertRedirect();
        $this->actingAs($courier)->getJson('/courier/dashboard')->assertStatus(403);

        // 2. Origin hub mismatch is rejected (422) and parcel current_hub_id is NOT overwritten
        $orderAtHubB = $this->createOrderAtHub($company, $hubB, $hubA, 'TRK-MISMATCH-1');
        $this->actingAs($company)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'order_ids' => [$orderAtHubB->id],
            'assigned_courier_id' => $courier->id,
        ])->assertStatus(422);
        $this->assertSame($hubB->id, $orderAtHubB->fresh()->current_hub_id);

        // 3. Exception-state manifest during transit can still report arrival and receive valid parcels
        $order1 = $this->createOrderAtHub($company, $hubA, $hubB, 'TRK-EXC-1');
        $order2 = $this->createOrderAtHub($company, $hubA, $hubB, 'TRK-EXC-2');

        $manifestService = app(\App\Services\TransferManifestService::class);
        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'order_ids' => [$order1->id, $order2->id],
            'assigned_courier_id' => $courier->id,
        ]);

        Sanctum::actingAs($courier);
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
            'tracking_number' => 'TRK-EXC-1',
        ])->assertOk();
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
            'tracking_number' => 'TRK-EXC-2',
        ])->assertOk();
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/depart")->assertOk();

        // Courier reports TRK-EXC-2 missing during transit -> manifest status = exception
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/report-issue", [
            'tracking_number' => 'TRK-EXC-2',
            'issue_type' => 'missing',
            'notes' => 'Parcel missing in transit',
        ])->assertOk();
        $this->assertSame('exception', $manifest->fresh()->status);

        // Courier can still report arrival while manifest is in exception state
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/arrive")
            ->assertOk();
        $this->assertNotNull($manifest->fresh()->arrived_at);

        // Receiving hub receives TRK-EXC-1 and cannot reclassify it as missing
        $manifestService->verifyReceivingParcel(
            $manifest->fresh(),
            'TRK-EXC-1',
            \App\Models\TransferManifestItem::RECEIVING_RECEIVED,
            $company
        );
        $this->assertSame('received_at_hub', $order1->fresh()->status);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $manifestService->verifyReceivingParcel(
            $manifest->fresh(),
            'TRK-EXC-1',
            \App\Models\TransferManifestItem::RECEIVING_MISSING,
            $company,
            'Should be blocked'
        );
    }

    public function test_staging_eligibility_destination_formatting_and_draft_manifest_append_remove(): void
    {
        $senderCompany = $this->createLogisticsCompany('Echo', 'echo@logistics.test');
        $receiverCompany = $this->createLogisticsCompany('Foxtrot', 'foxtrot@logistics.test');
        $hubA = $this->createHub($senderCompany, 'Echo Hub A', 'ECH-A');
        $hubB = $this->createHub($senderCompany, 'Echo Hub B', 'ECH-B');
        $hubC = $this->createHub($receiverCompany, 'Foxtrot Hub C', 'FOX-C');
        $courier = $this->createPersonnel($senderCompany, 'courier', 'courier.echo@test.com');

        $order1 = $this->createOrderAtHub($senderCompany, $hubA, $hubB, 'TRK-STG-1');
        $order2 = $this->createOrderAtHub($senderCompany, $hubA, $hubB, 'TRK-STG-2');
        $orderDiffRoute = $this->createOrderAtHub($senderCompany, $hubA, $hubC, 'TRK-STG-3');

        // Same-hub parcel already at destination must NOT appear in transfer queue
        $orderAlreadyAtDest = $this->createOrderAtHub($senderCompany, $hubB, $hubB, 'TRK-AT-DEST');

        // Parcel with missing destination address & missing destination hub -> Routing Review Required
        $orderMissingRouting = $this->createOrderAtHub($senderCompany, $hubA, $hubB, 'TRK-REVIEW-1');
        $orderMissingRouting->address->update([
            'barangay_name' => '',
            'municipality_name' => '',
            'province_name' => '',
        ]);
        $orderMissingRouting->update([
            'destination_hub_id' => null,
            'shipping_city' => null,
            'shipping_province' => null,
        ]);
        $this->assertSame('Destination Unavailable — Requires Review', $orderMissingRouting->fresh()->formatted_destination);

        // 1. Unassigned eligible parcels appear, already-at-dest & incomplete-routing are excluded from eligibleForManifest
        $indexRes = $this->actingAs($senderCompany)->get('/logistics/transfers');
        $indexRes->assertOk();
        $indexRes->assertSee('AVAILABLE PARCELS FOR TRANSFER (3)');
        $indexRes->assertSee('TRK-STG-1');
        $indexRes->assertSee('TRK-STG-2');
        $indexRes->assertSee('Routing Review Required');
        $indexRes->assertDontSee('TRK-AT-DEST');

        // 2. Create draft manifest with order1 -> order1 disappears from eligible list
        $this->actingAs($senderCompany)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'order_ids' => [$order1->id],
        ])->assertRedirect();

        $manifest = TransferManifest::firstOrFail();
        $this->assertSame('draft', $manifest->status);

        $afterDraftRes = $this->actingAs($senderCompany)->get('/logistics/transfers');
        $afterDraftRes->assertSee('AVAILABLE PARCELS FOR TRANSFER (2)');
        $eligibleIds = $afterDraftRes->viewData('eligibleForManifest')->pluck('id')->all();
        $this->assertNotContains($order1->id, $eligibleIds);
        $this->assertContains($order2->id, $eligibleIds);

        // 3. Duplicate manifest assignment for order1 is rejected (422)
        $this->actingAs($senderCompany)->post('/logistics/transfers/manifests', [
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'order_ids' => [$order1->id],
        ])->assertStatus(422);

        // 4. Incompatible route parcel cannot be appended to draft manifest (422)
        $this->actingAs($senderCompany)->post("/logistics/transfers/manifests/{$manifest->id}/parcels", [
            'order_ids' => [$orderDiffRoute->id],
        ])->assertStatus(422);

        // 5. Compatible parcel (order2) CAN be appended to draft manifest
        $this->actingAs($senderCompany)->post("/logistics/transfers/manifests/{$manifest->id}/parcels", [
            'order_ids' => [$order2->id],
        ])->assertRedirect();
        $this->assertSame(2, $manifest->fresh()->total_parcels);

        // 6. Removing order2 from draft manifest returns it to eligible list
        $item2 = $manifest->items()->where('order_id', $order2->id)->firstOrFail();
        $this->actingAs($senderCompany)->delete("/logistics/transfers/manifests/{$manifest->id}/items/{$item2->id}")
            ->assertRedirect();
        $this->assertSame(1, $manifest->fresh()->total_parcels);

        $afterRemoveIds = $this->actingAs($senderCompany)->get('/logistics/transfers')
            ->viewData('eligibleForManifest')->pluck('id')->all();
        $this->assertContains($order2->id, $afterRemoveIds);

        // 7. Assign courier, load, depart, arrive, complete -> order1 does NOT reappear
        $manifestService = app(\App\Services\TransferManifestService::class);
        $manifestService->assignCourier($manifest->fresh(), $courier->id, $senderCompany);
        $manifestService->scanLoadingParcel($manifest->fresh(), $order1->tracking_number, $courier);
        $manifestService->confirmDeparture($manifest->fresh(), $courier);

        // Dispatched manifest cannot accept additional parcels
        $this->actingAs($senderCompany)->post("/logistics/transfers/manifests/{$manifest->id}/parcels", [
            'order_ids' => [$order2->id],
        ])->assertStatus(422);

        $manifestService->markArrived($manifest->fresh(), $courier);
        $manifestService->verifyReceivingParcel(
            $manifest->fresh(),
            $order1->tracking_number,
            \App\Models\TransferManifestItem::RECEIVING_RECEIVED,
            $senderCompany
        );
        $this->assertSame('completed', $manifest->fresh()->status);

        $afterCompleteIds = $this->actingAs($senderCompany)->get('/logistics/transfers')
            ->viewData('eligibleForManifest')->pluck('id')->all();
        $this->assertNotContains($order1->id, $afterCompleteIds);
    }

    public function test_accepting_missing_parcel_with_already_received_hub_transfer_on_same_manifest_succeeds(): void
    {
        $company = $this->createLogisticsCompany('Delta', 'delta@logistics.test');
        $hubA = $this->createHub($company, 'Calamba Hub', 'DEL-CAL');
        $hubB = $this->createHub($company, 'Sta Cruz Hub', 'DEL-STC');
        $courier = $this->createPersonnel($company, 'courier', 'courier@delta.test');
        $order = $this->createOrderAtHub($company, $hubA, $hubB, 'TRK-RECV-SYNC-1');

        $manifestService = app(\App\Services\TransferManifestService::class);
        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'order_ids' => [$order->id],
            'assigned_courier_id' => $courier->id,
        ]);

        $manifestService->scanLoadingParcel($manifest, $order->tracking_number, $courier);
        $manifestService->confirmDeparture($manifest->fresh(), $courier);
        $manifestService->markArrived($manifest->fresh(), $courier);

        // Simulate a parcel marked missing on the manifest while its HubTransfer for this manifest was already received
        $item = $manifest->items()->firstOrFail();
        $item->hubTransfer->update([
            'status' => 'received',
            'received_at' => now(),
            'received_by' => $company->id,
        ]);
        $item->update(['receiving_status' => \App\Models\TransferManifestItem::RECEIVING_MISSING]);
        $manifest->update(['status' => \App\Models\TransferManifest::STATUS_EXCEPTION]);

        $this->actingAs($company)->post("/logistics/transfers/manifests/{$manifest->id}/verify-parcel", [
            'tracking_number' => $order->tracking_number,
            'classification' => 'received',
        ])->assertRedirect();

        $this->assertSame('received', $item->fresh()->receiving_status);
        $this->assertSame('completed', $manifest->fresh()->status);
    }
}
