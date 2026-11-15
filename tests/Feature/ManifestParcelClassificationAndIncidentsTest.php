<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ParcelIncident;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\TransferManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManifestParcelClassificationAndIncidentsTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): User
    {
        return User::create([
            'first_name' => $name,
            'last_name' => 'Logistics',
            'email' => strtolower(str_replace(' ', '_', $name)) . '_' . uniqid() . '@nexora.test',
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

    private function createHub(
        User $company,
        string $name,
        string $code,
        string $cityCode = '043426000',
        string $cityName = 'Santa Cruz',
        string $barangayCode = '043426001',
        string $barangayName = 'Poblacion I'
    ): Hub {
        return Hub::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => $cityCode,
            'city_name' => $cityName,
            'barangay_code' => $barangayCode,
            'barangay_name' => $barangayName,
            'is_active' => true,
        ]);
    }

    private function createPersonnel(User $company, string $role): User
    {
        $user = User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Staff',
            'email' => $role . '_' . uniqid() . '@nexora.test',
            'contact_number' => '0918' . random_int(1000000, 9999999),
            'birthday' => '1992-05-05',
            'age' => 33,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $user->id,
            'sorting_center_id' => $company->id,
            'logistics_company_id' => $company->id,
            'is_online' => true,
            'vehicle_type' => $role === 'rider' ? 'Motorcycle' : 'Box Truck',
            'plate_number' => 'NX-' . random_int(1000, 9999),
            'license_number' => 'N01-23-' . random_int(100000, 999999),
            'license_expiry_date' => now()->addYear(),
            'vehicle_verification_status' => 'verified',
        ]);

        return $user;
    }

    private function createOrderAtHub(User $company, Hub $originHub, Hub $destHub, string $tracking): Order
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'approval_status' => 'approved', 'account_status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'approval_status' => 'approved', 'account_status' => 'active']);

        $address = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '12 Rizal St',
            'house_number' => '12',
            'recipient_name' => $buyer->full_name,
            'recipient_phone' => '09171234567',
            'is_default' => true,
        ]);

        return Order::create([
            'order_number' => 'NX-ORD-' . uniqid(),
            'tracking_number' => $tracking,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'sorting_center_id' => $company->id,
            'pickup_logistics_id' => $company->id,
            'current_custody_logistics_id' => $company->id,
            'origin_hub_id' => $originHub->id,
            'current_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => 'sorted',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 450.00,
            'shipping_fee' => 50.00,
            'total' => 500.00,
            'placed_at' => now(),
        ]);
    }

    private function createArrivedManifestWithOrders(
        User $sender,
        Hub $originHub,
        User $receiver,
        Hub $destHub,
        array $trackings
    ): TransferManifest {
        $courier = $this->createPersonnel($sender, 'courier');
        $orders = [];
        foreach ($trackings as $trk) {
            $orders[] = $this->createOrderAtHub($sender, $originHub, $destHub, $trk);
        }

        $service = app(TransferManifestService::class);
        $manifest = $service->createManifest($sender, [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => collect($orders)->pluck('id')->all(),
            'assigned_courier_id' => $courier->id,
            'vehicle_plate_number' => 'TRK-555',
        ]);

        foreach ($orders as $order) {
            $service->scanLoadingParcel($manifest->fresh(), $order->tracking_number, $courier);
        }
        $service->confirmDeparture($manifest->fresh(), $courier);
        $service->markArrived($manifest->fresh(), $courier);

        return $manifest->fresh(['items.order', 'items.hubTransfer']);
    }

    public function test_manifest_table_renders_classify_parcel_condition_and_post_receipt_damage_controls(): void
    {
        Storage::fake('public');

        $sender = $this->createCompany('Sender Express');
        $receiver = $this->createCompany('Receiver Express');
        $originHub = $this->createHub($sender, 'Calamba Hub', 'CAL-T1');
        $destHub = $this->createHub($receiver, 'Laguna Hub', 'LAG-T1');

        $manifest = $this->createArrivedManifestWithOrders(
            $sender,
            $originHub,
            $receiver,
            $destHub,
            ['NX-CLS-001', 'NX-CLS-002']
        );

        $itemOne = $manifest->items->firstWhere('tracking_number', 'NX-CLS-001');
        $itemTwo = $manifest->items->firstWhere('tracking_number', 'NX-CLS-002');

        $this->actingAs($receiver);

        // Pending table shows Classify instead of quick Accept, plus Parcel Condition column
        $response = $this->get(route('logistics.transfers.manifests.show', $manifest));
        $response->assertStatus(200)
            ->assertSee('Parcel Condition')
            ->assertSee('Classify')
            ->assertSee('Good')
            ->assertDontSee('>Accept<', false);

        // Exception classification requires notes
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-CLS-001',
            'classification' => 'damaged',
            'notes' => '',
        ])->assertStatus(422);

        // Classify item 1 as received cleanly
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-CLS-001',
            'classification' => 'received',
            'notes' => 'Outer seal intact on arrival.',
        ])->assertSessionHas('status');

        $itemOne->refresh();
        $originalReceivedAt = $itemOne->received_at;
        $originalReceivedBy = $itemOne->received_by;

        $this->assertSame(TransferManifestItem::RECEIVING_RECEIVED, $itemOne->receiving_status);
        $this->assertSame($receiver->id, $itemOne->order->fresh()->current_custody_logistics_id);

        // Received parcel without damage shows Report Damage & Report Missing (not static Verified)
        $afterReceiveResponse = $this->get(route('logistics.transfers.manifests.show', $manifest));
        $afterReceiveResponse->assertStatus(200)
            ->assertSee('Report Damage')
            ->assertSee('Report Missing')
            ->assertDontSee('>Verified<', false);

        // Post-receipt damage report works after receipt and saves damage type, description & evidence
        $photo = UploadedFile::fake()->create('crushed-box.jpg', 240, 'image/jpeg');
        $this->post(route('logistics.transfers.manifests.items.report-damage', [$manifest, $itemOne]), [
            'damage_type' => 'crushed_packaging',
            'description' => 'Corner crushed discovered during sorting putaway.',
            'evidence' => $photo,
        ])->assertSessionHas('status');

        $itemOne->refresh();
        // Original receiving classification, timestamp, operator & custody MUST remain intact!
        $this->assertSame(TransferManifestItem::RECEIVING_RECEIVED, $itemOne->receiving_status);
        $this->assertEquals($originalReceivedAt->timestamp, $itemOne->received_at->timestamp);
        $this->assertSame($originalReceivedBy, $itemOne->received_by);
        $this->assertSame($receiver->id, $itemOne->order->fresh()->current_custody_logistics_id);

        $incident = ParcelIncident::where('manifest_item_id', $itemOne->id)->first();
        $this->assertNotNull($incident);
        $this->assertSame(ParcelIncident::TYPE_DAMAGE, $incident->incident_type);
        $this->assertSame('crushed_packaging', $incident->damage_type);
        $this->assertSame(ParcelIncident::STAGE_POST_RECEIPT, $incident->source_stage);
        $this->assertSame(ParcelIncident::STATUS_OPEN, $incident->investigation_status);
        Storage::disk('public')->assertExists($incident->evidence_path);

        // Duplicate open damage report is prevented
        $this->postJson(route('logistics.transfers.manifests.items.report-damage', [$manifest, $itemOne]), [
            'damage_type' => 'water_damage',
            'description' => 'Duplicate attempt should be blocked.',
        ])->assertStatus(422);

        // Manifest table now shows Damage Reported badge + View Damage Report button + Received status preserved
        $afterDamageResponse = $this->get(route('logistics.transfers.manifests.show', $manifest));
        $afterDamageResponse->assertStatus(200)
            ->assertSee('Damage Reported')
            ->assertSee('View Damage Report');

        // Audit log & OrderStatusHistory preserve both receipt and post-receipt damage report
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'manifest_parcel_damage_reported',
            'auditable_type' => TransferManifest::class,
            'auditable_id' => $manifest->id,
        ]);
        $this->assertTrue(
            OrderStatusHistory::where('order_id', $itemOne->order_id)
                ->where('note', 'like', '%Post-Receipt Damage%')
                ->exists()
        );

        // Normal automatic scan for itemTwo continues working
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-CLS-002',
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'received')
          ->assertJsonPath('newly_received', true)
          ->assertJsonPath('item.condition_summary.label', 'Good');

        // Even after manifest becomes completed, itemTwo can still report post-receipt damage
        $this->assertSame(TransferManifest::STATUS_COMPLETED, $manifest->fresh()->status);
        $this->post(route('logistics.transfers.manifests.items.report-damage', [$manifest, $itemTwo]), [
            'damage_type' => 'torn_open',
            'description' => 'Seal torn noticed after manifest completion.',
        ])->assertSessionHas('status');
    }

    public function test_missing_parcel_lifecycle_blocks_auto_scan_and_dispatch_until_found_and_resolved(): void
    {
        $sender = $this->createCompany('Sender North');
        $receiver = $this->createCompany('Receiver South');
        $originHub = $this->createHub($sender, 'Hub North', 'HN-01', '043405000', 'Calamba', '043405001', 'Real');
        $destHub = $this->createHub($receiver, 'Hub South', 'HS-01');
        $otherHub = $this->createHub($receiver, 'Hub East', 'HE-01', '043424000', 'San Pablo', '043424001', 'Poblacion');
        $rider = $this->createPersonnel($receiver, 'rider');

        $manifest = $this->createArrivedManifestWithOrders(
            $sender,
            $originHub,
            $receiver,
            $destHub,
            ['NX-MIS-001', 'NX-MIS-002']
        );

        $itemMissing = $manifest->items->firstWhere('tracking_number', 'NX-MIS-001');
        $itemPostLost = $manifest->items->firstWhere('tracking_number', 'NX-MIS-002');

        $this->actingAs($receiver);

        // 1. Classify pending item 1 as missing with required notes
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-MIS-001',
            'classification' => 'missing',
            'notes' => 'Parcel absent during truck unloading at dock 2.',
        ])->assertSessionHas('status');

        $missingIncident = ParcelIncident::where('manifest_item_id', $itemMissing->id)->firstOrFail();
        $this->assertSame(ParcelIncident::TYPE_MISSING, $missingIncident->incident_type);
        $this->assertSame(ParcelIncident::STATUS_OPEN, $missingIncident->investigation_status);
        $this->assertSame($destHub->id, $missingIncident->hub_id);
        $this->assertSame($receiver->id, $missingIncident->reported_by);

        // Red Missing badge displayed in manifest table
        $this->get(route('logistics.transfers.manifests.show', $manifest))
            ->assertStatus(200)
            ->assertSee('Missing')
            ->assertSee('Mark Found');

        // 2. Automatic barcode scan MUST NOT auto-receive an unresolved missing parcel
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-MIS-001',
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'flagged_for_investigation');

        // 3. Transition missing incident: Open -> Under Investigation -> Found
        $this->patch(route('logistics.transfers.manifests.incidents.update-status', [$manifest, $missingIncident]), [
            'investigation_status' => ParcelIncident::STATUS_UNDER_INVESTIGATION,
            'resolution_notes' => 'Reviewing bay CCTV footage.',
        ])->assertSessionHas('status');

        $this->get(route('logistics.transfers.manifests.show', $manifest))
            ->assertSee('Under Investigation');

        $this->patch(route('logistics.transfers.manifests.incidents.update-status', [$manifest, $missingIncident]), [
            'investigation_status' => ParcelIncident::STATUS_FOUND,
            'resolution_notes' => 'Located behind pallet #4.',
        ])->assertSessionHas('status');

        $missingIncident->refresh();
        $this->assertSame(ParcelIncident::STATUS_FOUND, $missingIncident->investigation_status);
        $this->assertNotNull($missingIncident->found_at);

        // Complete Receiving action now appears; manual completion transitions to Received & Resolved
        $this->get(route('logistics.transfers.manifests.show', $manifest))
            ->assertSee('Complete Receiving');

        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-MIS-001',
            'classification' => 'received',
            'notes' => 'Verified intact after recovery.',
        ])->assertSessionHas('status');

        $itemMissing->refresh();
        $missingIncident->refresh();
        $this->assertSame(TransferManifestItem::RECEIVING_RECEIVED, $itemMissing->receiving_status);
        $this->assertSame(ParcelIncident::STATUS_RESOLVED, $missingIncident->investigation_status);
        $this->assertNotNull($missingIncident->resolved_at);
        $this->assertSame($receiver->id, $itemMissing->order->fresh()->current_custody_logistics_id);

        // 4. Receive item 2, then report it MISSING post-receipt
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-MIS-002',
            'classification' => 'received',
        ])->assertSessionHas('status');

        $itemPostLost->refresh();
        $origReceivedAt = $itemPostLost->received_at;

        $this->post(route('logistics.transfers.manifests.items.report-missing', [$manifest, $itemPostLost]), [
            'description' => 'Misplaced from staging rack B before rider dispatch.',
        ])->assertSessionHas('status');

        $itemPostLost->refresh();
        $postMissingIncident = ParcelIncident::where('manifest_item_id', $itemPostLost->id)
            ->where('incident_type', ParcelIncident::TYPE_MISSING)
            ->firstOrFail();

        $this->assertSame(TransferManifestItem::RECEIVING_RECEIVED, $itemPostLost->receiving_status);
        $this->assertEquals($origReceivedAt->timestamp, $itemPostLost->received_at->timestamp);
        $this->assertSame(ParcelIncident::STAGE_POST_RECEIPT, $postMissingIncident->source_stage);
        $this->assertSame(ParcelIncident::STATUS_OPEN, $postMissingIncident->investigation_status);

        // Duplicate unresolved missing incident is rejected
        $this->postJson(route('logistics.transfers.manifests.items.report-missing', [$manifest, $itemPostLost]), [
            'description' => 'Duplicate missing report.',
        ])->assertStatus(422);

        // 5. Unresolved incident blocks BOTH normal rider dispatch AND creating a new transfer manifest
        $lostOrder = $itemPostLost->order->fresh();
        $lostOrder->update(['status' => 'sorted', 'sorting_center_id' => $receiver->id]);

        $this->post(route('logistics.assignments.assign', $lostOrder), [
            'courier_id' => $rider->id,
            'override_reason' => 'Dispatch attempt while missing',
        ])->assertSessionHasErrors('courier_id');

        $this->assertSame('sorted', $lostOrder->fresh()->status);

        $this->post(route('logistics.transfers.manifests.store'), [
            'origin_hub_id' => $destHub->id,
            'destination_hub_id' => $otherHub->id,
            'order_ids' => [$lostOrder->id],
        ])->assertStatus(422);

        // 6. Logistics exception reports & manifest summaries show the missing/damage incidents
        $this->get(route('logistics.reports.index', ['tab' => 'exceptions']))
            ->assertStatus(200)
            ->assertSee('NX-MIS-002')
            ->assertSee('Missing');

        $this->get(route('logistics.reports.export', ['type' => 'exceptions']))
            ->assertStatus(200);

        $this->get(route('logistics.transfers.index'))
            ->assertStatus(200)
            ->assertSee('1 Missing');

        // 7. Once resolved, normal dispatch succeeds cleanly
        $this->patch(route('logistics.transfers.manifests.incidents.update-status', [$manifest, $postMissingIncident]), [
            'investigation_status' => ParcelIncident::STATUS_FOUND,
            'resolution_notes' => 'Found in overflow bin.',
        ])->assertSessionHas('status');

        $this->patch(route('logistics.transfers.manifests.incidents.update-status', [$manifest, $postMissingIncident]), [
            'investigation_status' => ParcelIncident::STATUS_RESOLVED,
            'resolution_notes' => 'Parcel scanned back onto dispatch rack.',
        ])->assertSessionHas('status');

        $this->post(route('logistics.assignments.assign', $lostOrder), [
            'courier_id' => $rider->id,
            'override_reason' => 'Incident resolved and cleared for delivery',
        ])->assertSessionHas('status');

        $this->assertSame('assigned_to_rider', $lostOrder->fresh()->status);
    }

    public function test_unauthorized_operators_cannot_modify_parcel_conditions_or_incidents(): void
    {
        $sender = $this->createCompany('Sender Auth');
        $receiver = $this->createCompany('Receiver Auth');
        $intruder = $this->createCompany('Intruder Logistics');
        $originHub = $this->createHub($sender, 'Hub A1', 'HA-01');
        $destHub = $this->createHub($receiver, 'Hub B1', 'HB-01');

        $manifest = $this->createArrivedManifestWithOrders(
            $sender,
            $originHub,
            $receiver,
            $destHub,
            ['NX-AUTH-001']
        );
        $item = $manifest->items->first();

        // Receive parcel as authorized receiver
        $this->actingAs($receiver)->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-AUTH-001',
            'classification' => 'received',
        ])->assertSessionHas('status');

        // Sender cannot post-receipt report damage or missing on receiver's manifest
        $this->actingAs($sender)->postJson(route('logistics.transfers.manifests.items.report-damage', [$manifest, $item]), [
            'damage_type' => 'other',
            'description' => 'Sender attempt should fail.',
        ])->assertStatus(403);

        // Intruder cannot classify, report damage, or report missing
        $this->actingAs($intruder)->postJson(route('logistics.transfers.manifests.items.report-missing', [$manifest, $item]), [
            'description' => 'Intruder attempt should fail.',
        ])->assertStatus(403);

        // Receiver logs an incident
        $this->actingAs($receiver)->post(route('logistics.transfers.manifests.items.report-damage', [$manifest, $item]), [
            'damage_type' => 'broken_contents',
            'description' => 'Box damp underneath.',
        ])->assertSessionHas('status');

        $incident = ParcelIncident::where('manifest_item_id', $item->id)->firstOrFail();

        // Intruder and sender cannot mutate investigation status
        $this->actingAs($intruder)->patchJson(route('logistics.transfers.manifests.incidents.update-status', [$manifest, $incident]), [
            'investigation_status' => ParcelIncident::STATUS_RESOLVED,
            'resolution_notes' => 'Unauthorized resolve attempt.',
        ])->assertStatus(403);

        $this->actingAs($sender)->patchJson(route('logistics.transfers.manifests.incidents.update-status', [$manifest, $incident]), [
            'investigation_status' => ParcelIncident::STATUS_RESOLVED,
            'resolution_notes' => 'Sender resolve attempt.',
        ])->assertStatus(403);

        $this->assertSame(ParcelIncident::STATUS_OPEN, $incident->fresh()->investigation_status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'parcel_incident_status_updated',
            'operator_id' => $intruder->id,
        ]);
        $this->assertSame(1, AuditLog::where('action', 'manifest_parcel_damage_reported')->where('auditable_id', $manifest->id)->count());
    }
}
