<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\CourierProfile;
use App\Models\Dispute;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ParcelIncident;
use App\Models\ParcelIncidentEvent;
use App\Models\Product;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Notifications\ParcelIncidentNotification;
use App\Services\CodCollectionService;
use App\Services\CodFinancialService;
use App\Services\CompensationPolicyService;
use App\Services\ParcelIncidentService;
use App\Services\TransferManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParcelIncidentLifecycleAndRecoveryTest extends TestCase
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

    private function createHub(User $company, string $name, string $code): Hub
    {
        return Hub::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043405000',
            'city_name' => 'Calamba',
            'barangay_code' => '043405001',
            'barangay_name' => 'Real',
            'is_active' => true,
        ]);
    }

    private function createPersonnel(User $company, string $role): User
    {
        $user = User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Worker_' . uniqid(),
            'email' => $role . '_' . uniqid() . '@nexora.test',
            'contact_number' => '0918' . random_int(1000000, 9999999),
            'birthday' => '1993-04-10',
            'age' => 32,
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
            'vehicle_type' => $role === 'rider' ? 'Motorcycle' : 'Van',
            'plate_number' => 'NX-' . random_int(1000, 9999),
            'license_number' => 'N01-24-' . random_int(100000, 999999),
            'license_expiry_date' => now()->addYear(),
            'vehicle_verification_status' => 'verified',
        ]);

        return $user;
    }

    private function createOrderFixture(
        User $company,
        Hub $originHub,
        Hub $destHub,
        string $tracking,
        ?User $buyer = null,
        ?User $seller = null
    ): Order {
        $buyer ??= User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);

        $seller ??= User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '45 Market St',
            'house_number' => '45',
            'recipient_name' => $buyer->full_name,
            'recipient_phone' => '09171234567',
            'is_default' => true,
        ]);

        return Order::create([
            'order_number' => 'NX-LIFE-' . uniqid(),
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
            'subtotal' => 900.00,
            'shipping_fee' => 80.00,
            'total' => 980.00,
            'placed_at' => now(),
        ]);
    }

    public function test_scenario_a_and_b_courier_loading_and_transit_incidents_require_evidence_and_block_self_resolve(): void
    {
        Storage::fake('public');

        $company = $this->createCompany('Metro Logistics');
        $originHub = $this->createHub($company, 'Calamba Hub', 'CAL-A');
        $destHub = $this->createHub($company, 'Santa Cruz Hub', 'STC-B');
        $courier = $this->createPersonnel($company, 'courier');

        $orderOne = $this->createOrderFixture($company, $originHub, $destHub, 'NX-SCEN-AB1');
        $orderTwo = $this->createOrderFixture($company, $originHub, $destHub, 'NX-SCEN-AB2');

        $manifestService = app(TransferManifestService::class);
        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => [$orderOne->id, $orderTwo->id],
            'assigned_courier_id' => $courier->id,
            'vehicle_plate_number' => 'NXR-101',
        ]);

        Sanctum::actingAs($courier);

        // Scenario A: Courier loads parcel 1 and reports parcel 2 missing during loading
        $manifestService->scanLoadingParcel($manifest->fresh(), 'NX-SCEN-AB1', $courier);

        $loadingIssueRes = $this->postJson(route('api.mobile.transfer-courier.manifests.report-issue', $manifest->id), [
            'tracking_number' => 'NX-SCEN-AB2',
            'issue_type' => 'missing',
            'notes' => 'Parcel missing from loading cage at bay 3.',
            'last_known_location' => 'Calamba Staging Bay 3',
        ]);

        $loadingIssueRes->assertStatus(200)
            ->assertJsonPath('data.loading_status', TransferManifestItem::LOADING_ISSUE)
            ->assertJsonPath('data.incident.incident_type', ParcelIncident::TYPE_MISSING)
            ->assertJsonPath('data.incident.investigation_status', ParcelIncident::STATUS_OPEN);

        $loadingIncident = ParcelIncident::where('tracking_number', 'NX-SCEN-AB2')->firstOrFail();
        $this->assertNotEmpty($loadingIncident->incident_number);
        $this->assertSame(ParcelIncident::STAGE_MANIFEST_LOADING, $loadingIncident->source_stage);
        $this->assertSame(ParcelIncident::RESTRICTION_HOLD_DISPATCH, $orderTwo->fresh()->handling_status);

        // Unresolved loading issue MUST block confirmDeparture even if tried directly
        $this->postJson(route('api.mobile.transfer-courier.manifests.depart', $manifest->id), [
            'vehicle_plate_number' => 'NXR-101',
        ])->assertStatus(422);

        // Logistics removes affected parcel from loading manifest so clean manifest can depart
        $itemTwo = $manifest->items()->where('tracking_number', 'NX-SCEN-AB2')->firstOrFail();
        $manifestService->removeParcelFromManifest($manifest->fresh(), $itemTwo, $company);
        $manifestService->confirmDeparture($manifest->fresh(), $courier, 'NXR-101');

        // Scenario B: Courier reports transit damage on loaded parcel 1 with photo & location
        $photo = UploadedFile::fake()->create('transit-wet.jpg', 320, 'image/jpeg');
        $transitRes = $this->post(route('api.mobile.transfer-courier.manifests.report-issue', $manifest->id), [
            'tracking_number' => 'NX-SCEN-AB1',
            'issue_type' => 'damaged',
            'notes' => 'Roof leak dripped onto carton near SLEX exit.',
            'last_known_location' => 'SLEX KM 42 Tollgate',
            'evidence_photo' => $photo,
        ], ['Accept' => 'application/json']);

        $transitRes->assertStatus(200)
            ->assertJsonPath('data.incident.last_known_location', 'SLEX KM 42 Tollgate')
            ->assertJsonCount(1, 'data.incident.events');

        $transitIncident = ParcelIncident::where('tracking_number', 'NX-SCEN-AB1')->firstOrFail();
        $this->assertSame(ParcelIncident::STAGE_COURIER_TRANSIT, $transitIncident->source_stage);
        Storage::disk('public')->assertExists($transitIncident->evidence_path);

        // Couriers MUST NOT be able to self-resolve or declare loss via logistics exception endpoints
        $this->postJson(route('logistics.exceptions.disposition', $transitIncident), [
            'disposition' => ParcelIncident::DISPOSITION_DECLARED_LOST,
            'resolution_notes' => 'Courier trying to declare lost.',
        ])->assertStatus(403);
    }

    public function test_scenario_c_and_e_missing_recovery_eliminates_notes_only_close_and_quarantine_blocks_dispatch(): void
    {
        Notification::fake();
        Storage::fake('public');

        $company = $this->createCompany('Laguna HubNet');
        $originHub = $this->createHub($company, 'Origin Hub', 'ORG-CE');
        $destHub = $this->createHub($company, 'Dest Hub', 'DST-CE');
        $courier = $this->createPersonnel($company, 'courier');
        $rider = $this->createPersonnel($company, 'rider');

        $orderMissing = $this->createOrderFixture($company, $originHub, $destHub, 'NX-SCEN-C1');
        $orderDamaged = $this->createOrderFixture($company, $originHub, $destHub, 'NX-SCEN-E1');

        $manifestService = app(TransferManifestService::class);
        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => [$orderMissing->id, $orderDamaged->id],
            'assigned_courier_id' => $courier->id,
        ]);

        $manifestService->scanLoadingParcel($manifest->fresh(), 'NX-SCEN-C1', $courier);
        $manifestService->scanLoadingParcel($manifest->fresh(), 'NX-SCEN-E1', $courier);
        $manifestService->confirmDeparture($manifest->fresh(), $courier);
        $manifestService->markArrived($manifest->fresh(), $courier);

        $this->actingAs($company);

        // Scenario C: Classify NX-SCEN-C1 missing at receiving
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-SCEN-C1',
            'classification' => 'missing',
            'notes' => 'Missing during unload at receiving dock.',
        ])->assertSessionHas('status');

        $missingIncident = ParcelIncident::where('tracking_number', 'NX-SCEN-C1')->firstOrFail();

        // Attempt shortcut clearing via notes-only update or inspected_and_cleared MUST FAIL
        $this->expectException(ValidationException::class);
        try {
            app(ParcelIncidentService::class)->applyDisposition(
                $missingIncident,
                $company,
                ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED,
                'Trying to clear without physical recovery.',
                null,
                true
            );
        } finally {
            $missingIncident->refresh();
            $this->assertSame(ParcelIncident::STATUS_OPEN, $missingIncident->investigation_status);
        }

        // Physical recovery with evidence verifies receipt and clears restriction
        $recoveryPhoto = UploadedFile::fake()->create('recovered-parcel.jpg', 180, 'image/jpeg');
        $this->post(route('logistics.exceptions.recover', $missingIncident), [
            'recovery_location' => 'Overflow Cage B2',
            'notes' => 'Found behind intake roller and barcode scanned intact.',
            'confirm_physical_receipt' => 1,
            'recovery_evidence' => $recoveryPhoto,
        ])->assertSessionHas('status');

        $missingIncident->refresh();
        $this->assertSame(ParcelIncident::STATUS_RESOLVED, $missingIncident->investigation_status);
        $this->assertSame(ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED, $missingIncident->disposition);
        $this->assertSame(ParcelIncident::RESTRICTION_CLEARED, $missingIncident->handling_restriction);
        $this->assertNotNull($missingIncident->recovered_at);
        Storage::disk('public')->assertExists($missingIncident->recovery_evidence_path);

        // Scenario E: Damaged parcel accepted into Quarantine blocks rider dispatch & COD collection
        $damagePhoto = UploadedFile::fake()->create('crushed.jpg', 200, 'image/jpeg');
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-SCEN-E1',
            'classification' => 'damaged',
            'damage_type' => 'crushed_packaging',
            'accept_into_quarantine' => 1,
            'notes' => 'Side panel crushed; held in quarantine.',
            'evidence' => $damagePhoto,
        ])->assertSessionHas('status');

        $damageIncident = ParcelIncident::where('tracking_number', 'NX-SCEN-E1')->firstOrFail();
        $this->assertSame(ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE, $damageIncident->disposition);
        $this->assertSame(ParcelIncident::RESTRICTION_QUARANTINED, $damageIncident->handling_restriction);

        $orderDamaged->refresh();
        $this->assertSame(ParcelIncident::RESTRICTION_QUARANTINED, $orderDamaged->handling_status);

        // Rider dispatch MUST be rejected while quarantined
        $this->post(route('logistics.assignments.assign', $orderDamaged), [
            'courier_id' => $rider->id,
            'override_reason' => 'Attempt dispatch of quarantined parcel',
        ])->assertSessionHasErrors('courier_id');

        // Financial safeguard: COD collection blocked while financial hold is active
        $codResult = app(CodCollectionService::class)->recordCollectionOnDelivery(
            $orderDamaged->fresh(),
            $rider,
            980.00
        );
        $this->assertFalse($codResult['ok']);

        // Repack & Clear lifts quarantine & financial hold so rider dispatch can proceed cleanly
        $this->post(route('logistics.exceptions.disposition', $damageIncident), [
            'disposition' => ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED,
            'resolution_notes' => 'Contents inspected intact; repacked into fresh mailer.',
            'close_immediately' => 1,
        ])->assertSessionHas('status');

        $orderDamaged->refresh();
        $this->assertFalse($orderDamaged->hasUnresolvedHandlingRestrictions());
        $this->post(route('logistics.assignments.assign', $orderDamaged), [
            'courier_id' => $rider->id,
            'override_reason' => 'Quarantine cleared after repack.',
        ])->assertSessionHas('status');
    }

    public function test_scenario_f_and_g_wrong_hub_reroute_closed_with_exceptions_and_single_courier_payout(): void
    {
        Notification::fake();

        $company = $this->createCompany('South Hub Express');
        $originHub = $this->createHub($company, 'Calamba Hub', 'CAL-FG');
        $intendedHub = $this->createHub($company, 'Biñan Hub', 'BIN-FG');
        $wrongHub = $this->createHub($company, 'Cabuyao Hub', 'CAB-FG');
        $courier = $this->createPersonnel($company, 'courier');

        $this->actingAs($company);

        // Scenario F: Misrouted parcel scanned at wrongHub -> Wrong-Hub incident -> Authorized Reroute leg
        $misroutedOrder = $this->createOrderFixture($company, $originHub, $intendedHub, 'NX-SCEN-F1');
        $misroutedOrder->update(['status' => 'in_transit_hub', 'current_hub_id' => $wrongHub->id]);

        $this->post(route('logistics.incoming.wrong-hub', $misroutedOrder), [
            'actual_hub_id' => $wrongHub->id,
            'notes' => 'Arrived in Cabuyao sort bin instead of Biñan.',
            'location' => 'Cabuyao Intake Cage',
        ])->assertRedirect();

        $wrongIncident = ParcelIncident::where('order_id', $misroutedOrder->id)
            ->where('incident_type', ParcelIncident::TYPE_WRONG_HUB)
            ->firstOrFail();
        $this->assertSame(ParcelIncident::RESTRICTION_REROUTE_REQUIRED, $wrongIncident->handling_restriction);

        $this->post(route('logistics.exceptions.reroute', $wrongIncident), [
            'target_hub_id' => $intendedHub->id,
            'notes' => 'Dispatched on morning shuttle back to Biñan Hub.',
        ])->assertSessionHas('status');

        $misroutedOrder->refresh();
        $wrongIncident->refresh();
        $this->assertSame($wrongHub->id, $misroutedOrder->rerouted_from_hub_id);
        $this->assertSame(1, $misroutedOrder->reroute_count);
        $this->assertSame(ParcelIncident::STATUS_RESOLVED, $wrongIncident->investigation_status);
        $this->assertSame(ParcelIncident::DISPOSITION_RESOLVED_BY_CORRECTION, $wrongIncident->disposition);
        $this->assertTrue(HubTransfer::where('order_id', $misroutedOrder->id)->where('receiver_hub_id', $intendedHub->id)->exists());

        // Scenario G: Close Manifest With Exceptions without fabricating receipts or overpaying courier
        $orderGood = $this->createOrderFixture($company, $originHub, $intendedHub, 'NX-SCEN-G1');
        $orderLost = $this->createOrderFixture($company, $originHub, $intendedHub, 'NX-SCEN-G2');

        $manifestService = app(TransferManifestService::class);
        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $intendedHub->id,
            'order_ids' => [$orderGood->id, $orderLost->id],
            'assigned_courier_id' => $courier->id,
        ]);

        $manifestService->scanLoadingParcel($manifest->fresh(), 'NX-SCEN-G1', $courier);
        $manifestService->scanLoadingParcel($manifest->fresh(), 'NX-SCEN-G2', $courier);
        $manifestService->confirmDeparture($manifest->fresh(), $courier);
        $manifestService->markArrived($manifest->fresh(), $courier);

        // Receive 1 cleanly, mark 1 missing and declare permanently lost
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-SCEN-G1',
            'classification' => 'received',
        ])->assertSessionHas('status');

        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-SCEN-G2',
            'classification' => 'missing',
            'notes' => 'Missing after seal check.',
        ])->assertSessionHas('status');

        $lostIncident = ParcelIncident::where('tracking_number', 'NX-SCEN-G2')->firstOrFail();
        $this->post(route('logistics.exceptions.disposition', $lostIncident), [
            'disposition' => ParcelIncident::DISPOSITION_DECLARED_LOST,
            'resolution_notes' => 'Exhausted bay search and CCTV audit.',
            'close_immediately' => 1,
        ])->assertSessionHas('status');

        // Reconcile & Close With Exceptions
        $this->post(route('logistics.transfers.manifests.close-with-exceptions', $manifest), [
            'reconciliation_notes' => '1 parcel verified received; 1 declared lost under claim review.',
        ])->assertSessionHas('status');

        $manifest->refresh();
        $this->assertSame(TransferManifest::STATUS_CLOSED_WITH_EXCEPTIONS, $manifest->status);

        // Missing item must NEVER get a fabricated 'received' status!
        $lostItem = $manifest->items()->where('tracking_number', 'NX-SCEN-G2')->firstOrFail();
        $this->assertSame(TransferManifestItem::RECEIVING_MISSING, $lostItem->receiving_status);
        $this->assertNull($lostItem->received_at);

        // Courier payout respects pay_on_exception guard by default, then pays idempotently once if policy permits
        $compService = app(CompensationPolicyService::class);
        $this->assertNull($compService->creditTransferCourierForManifest($manifest->fresh(), true));

        $policy = $compService->getActivePolicy(\App\Models\CompensationPolicy::ROLE_COURIER, $company->id);
        $policy->update(['pay_on_exception' => true]);

        $payout1 = $compService->creditTransferCourierForManifest($manifest->fresh(), true);
        $payout2 = $compService->creditTransferCourierForManifest($manifest->fresh(), true);
        $this->assertNotNull($payout1);
        $this->assertSame('transfer_exception', $payout1->type);
        $this->assertSame(1, (int) ($payout1->rate_snapshot['delivered_parcels_count'] ?? 0));
        $this->assertSame($payout1->id, $payout2->id);
    }

    public function test_scenario_h_central_console_sla_dedup_buyer_tracking_confirm_guard_and_financial_hold(): void
    {
        Notification::fake();

        $company = $this->createCompany('Central Exception Corp');
        $originHub = $this->createHub($company, 'Origin Hub', 'ORG-H');
        $destHub = $this->createHub($company, 'Dest Hub', 'DST-H');
        $courier = $this->createPersonnel($company, 'courier');

        $buyer = User::factory()->create(['role' => 'buyer', 'approval_status' => 'approved', 'account_status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'approval_status' => 'approved', 'account_status' => 'active']);

        $order = $this->createOrderFixture($company, $originHub, $destHub, 'NX-SCEN-H1', $buyer, $seller);

        $manifestService = app(TransferManifestService::class);
        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'order_ids' => [$order->id],
            'assigned_courier_id' => $courier->id,
        ]);

        $manifestService->scanLoadingParcel($manifest->fresh(), 'NX-SCEN-H1', $courier);
        $manifestService->confirmDeparture($manifest->fresh(), $courier);
        $manifestService->markArrived($manifest->fresh(), $courier);

        $this->actingAs($company);
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-SCEN-H1',
            'classification' => 'damaged',
            'damage_type' => 'broken_contents',
            'accept_into_quarantine' => 1,
            'notes' => 'INTERNAL_STAFF_NAME_DO_NOT_LEAK rattle noticed by supervisor.',
        ])->assertSessionHas('status');

        $incident = ParcelIncident::where('tracking_number', 'NX-SCEN-H1')->firstOrFail();

        // Centralized Exceptions console index filters + detail + investigator assignment
        $this->get(route('logistics.exceptions.index', ['quarantined' => 1]))
            ->assertStatus(200)
            ->assertSee($incident->incident_number)
            ->assertSee('NX-SCEN-H1');

        $this->post(route('logistics.exceptions.assign', $incident), [
            'assigned_to' => $company->id,
            'notes' => 'Assigned to hub lead for containment.',
        ])->assertSessionHas('status');

        // Force due_at overdue and test deduplicated SLA alert firing
        $incident->update(['due_at' => now()->subHours(6)]);
        $this->post(route('logistics.exceptions.overdue-alerts'))->assertSessionHas('status');
        $sentFirst = Notification::sent($company, ParcelIncidentNotification::class)->count();
        $this->assertGreaterThan(0, $sentFirst);

        // Second call within cooldown MUST NOT send duplicate SLA notifications
        $this->post(route('logistics.exceptions.overdue-alerts'))->assertSessionHas('status');
        $sentAfterSecond = Notification::sent($company, ParcelIncidentNotification::class)->count();
        $this->assertSame($sentFirst, $sentAfterSecond);

        // Buyer API tracking_summary returns chronological events and sanitized exception text (no internal staff note)
        Sanctum::actingAs($buyer);
        $detailRes = $this->getJson(route('api.mobile.buyer.orders.show', $order));
        $detailRes->assertStatus(200)
            ->assertJsonPath('data.tracking_number', 'NX-SCEN-H1')
            ->assertJsonPath('data.tracking_summary.has_active_exception', true);
        $this->assertStringNotContainsString('INTERNAL_STAFF_NAME_DO_NOT_LEAK', $detailRes->getContent());

        // Buyer cannot confirm receipt while out_for_delivery!
        $order->update(['status' => 'out_for_delivery', 'handling_status' => null]);
        $this->postJson(route('api.mobile.buyer.orders.confirm-receipt', $order))
            ->assertStatus(422);

        // Web buyer confirm-receipt also blocked on out_for_delivery
        $this->actingAs($buyer)
            ->post(route('buyer.orders.confirm-receipt', $order))
            ->assertStatus(422);

        // Even while delivered, Seller financial overview excludes order while financial_hold_status = on_hold
        $order->update(['status' => 'delivered', 'delivered_at' => now()]);
        $sellerSummaryHold = app(CodFinancialService::class)->getSellerFinancialOverview($seller);
        $this->assertSame(0.0, (float) ($sellerSummaryHold['earningsWaitingLogistics'] ?? 0));
        $this->assertCount(0, app(CodFinancialService::class)->getEligibleOrdersForRemittance($company));
    }
}
