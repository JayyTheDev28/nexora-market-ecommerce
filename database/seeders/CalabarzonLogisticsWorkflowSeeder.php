<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Hub;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\TransferManifest;
use App\Models\User;
use App\Models\Zone;
use App\Services\InterRegionLogisticsService;
use App\Services\ShippingPricingService;
use App\Services\TransferManifestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Extends the NationalMarketplaceDemoSeeder dataset with 60 realistic CALABARZON
 * test parcels across 5 separate Logistics workflows (including 10 draft manifests
 * containing 30 transfer parcels), with zero completed deliveries, zero rider assignments,
 * and zero financial settlements/earnings.
 *
 * Safe & repeatable: never wipes genuine records, never runs migrate:fresh, and uses
 * deterministic order/tracking prefixes + manifest tags for idempotency.
 */
class CalabarzonLogisticsWorkflowSeeder extends Seeder
{
    private const CALABARZON_PROVINCES = [
        '043400000' => 'Laguna',
        '042100000' => 'Cavite',
        '041000000' => 'Batangas',
        '045600000' => 'Quezon',
        '045800000' => 'Rizal',
    ];

    private const FIXTURE_TAG = '[SEED FIXTURE]';

    private InterRegionLogisticsService $logisticsRouter;
    private ShippingPricingService $shippingPricing;
    private TransferManifestService $manifestService;

    /** @var array<string, int> */
    private array $preFinancialCounts = [];

    private const FINANCIAL_POD_TABLES = [
        'rider_earnings',
        'cod_collections',
        'cod_remittances',
        'courier_payouts',
        'seller_payout_requests',
        'commissions',
        'proof_of_deliveries',
    ];

    public function run(): void
    {
        $this->logisticsRouter = app(InterRegionLogisticsService::class);
        $this->shippingPricing = app(ShippingPricingService::class);
        $this->manifestService = app(TransferManifestService::class);

        $this->preFinancialCounts = $this->snapshotFinancialCounts();
        $this->verifyPrerequisites();

        $this->seedMerchantPickups();
        $this->seedUnsortedHubParcels();
        $this->seedSortedUnassignedParcels();
        $this->seedInternalHubToHubManifests();
        $this->seedInterCompanyManifests();

        $this->assertWorkflowDataset();
    }

    private function snapshotFinancialCounts(): array
    {
        $counts = [];
        foreach (self::FINANCIAL_POD_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    private function verifyPrerequisites(): void
    {
        foreach (self::CALABARZON_PROVINCES as $provinceCode => $provinceName) {
            $company = User::where('email', "logistics.{$provinceCode}@nexora.test")->first();
            if (! $company) {
                throw new RuntimeException("Missing CALABARZON logistics company for {$provinceName} ({$provinceCode}). Run NationalMarketplaceDemoSeeder first.");
            }
            if (Hub::where('logistics_company_id', $company->id)->where('is_active', true)->count() < 2) {
                throw new RuntimeException("Insufficient active hubs for {$provinceName}.");
            }
        }
    }

    /**
     * 1. Merchant Pickups — Awaiting Assignment (10 parcels, 2 per CALABARZON province).
     */
    private function seedMerchantPickups(): void
    {
        $seq = 0;
        $provinceCodes = array_keys(self::CALABARZON_PROVINCES);

        foreach ($provinceCodes as $pIdx => $provinceCode) {
            $company = User::where('email', "logistics.{$provinceCode}@nexora.test")->firstOrFail();
            $hubs = Hub::where('logistics_company_id', $company->id)->where('is_active', true)->orderBy('id')->get();
            $destProvinceCode = $provinceCodes[($pIdx + 1) % count($provinceCodes)];

            for ($k = 0; $k < 2; $k++) {
                $seq++;
                $hub = $hubs[$k % $hubs->count()];
                $seller = $this->findSellerForHub($hub, $k);
                $buyer = $this->findBuyerInProvince($destProvinceCode, $seq);

                $orderNo = sprintf('NX-CBZ-PKP-%02d', $seq);
                $trkNo = sprintf('NX-TRK-CBZ-PKP%02d', $seq);

                if (Order::where('order_number', $orderNo)->exists()) {
                    continue;
                }

                $order = $this->createSeededOrder($orderNo, $trkNo, $buyer, $seller, $seq);
                $order->update([
                    'status' => 'ready_for_pickup',
                    'pickup_courier_id' => null,
                    'pickup_rider_id' => null,
                    'delivery_courier_id' => null,
                    'delivery_rider_id' => null,
                    'current_hub_id' => null,
                    'current_custody_logistics_id' => $company->id,
                    'sorting_center_id' => $company->id,
                ]);

                $this->recordStatusTrail($order, $seller->id, [
                    ['placed', $buyer->id, self::FIXTURE_TAG . ' Synthetic order placed for pickup scheduling test.', now()->subHours(4)],
                    ['confirmed', $seller->id, self::FIXTURE_TAG . ' Seller confirmed order.', now()->subHours(3)],
                    ['preparing', $seller->id, self::FIXTURE_TAG . ' Seller packed parcel.', now()->subHours(2)],
                    ['ready_for_pickup', $seller->id, self::FIXTURE_TAG . ' Ready for Logistics pickup scheduling.', now()->subHours(1)],
                ]);
            }
        }
    }

    /**
     * 2. Parcel Sorting — Unsorted (10 parcels, 2 per CALABARZON province).
     * Physically received at destination hub, eligible for local sorting with no transfer needed.
     */
    private function seedUnsortedHubParcels(): void
    {
        $seq = 0;
        foreach (array_keys(self::CALABARZON_PROVINCES) as $provinceCode) {
            $company = User::where('email', "logistics.{$provinceCode}@nexora.test")->firstOrFail();

            for ($k = 0; $k < 2; $k++) {
                $seq++;
                [$seller, $buyer, $hub] = $this->findSameHubSellerAndBuyer($company, $k);

                $orderNo = sprintf('NX-CBZ-SRT-%02d', $seq);
                $trkNo = sprintf('NX-TRK-CBZ-SRT%02d', $seq);

                if (Order::where('order_number', $orderNo)->exists()) {
                    continue;
                }

                $order = $this->createSeededOrder($orderNo, $trkNo, $buyer, $seller, 20 + $seq);
                $order->update([
                    'status' => 'received_at_hub',
                    'origin_hub_id' => $hub->id,
                    'destination_hub_id' => $hub->id,
                    'current_hub_id' => $hub->id,
                    'pickup_logistics_id' => $company->id,
                    'delivery_logistics_id' => $company->id,
                    'current_custody_logistics_id' => $company->id,
                    'sorting_center_id' => $company->id,
                    'pickup_courier_id' => null,
                    'pickup_rider_id' => null,
                    'delivery_courier_id' => null,
                    'delivery_rider_id' => null,
                ]);

                $this->recordStatusTrail($order, $company->id, [
                    ['placed', $buyer->id, self::FIXTURE_TAG . ' Synthetic order placed.', now()->subHours(5)],
                    ['ready_for_pickup', $seller->id, self::FIXTURE_TAG . ' Staged by seller.', now()->subHours(4)],
                    ['received_at_hub', $company->id, self::FIXTURE_TAG . " Pre-positioned at destination hub {$hub->name} ({$hub->code}) awaiting sorting.", now()->subHours(2)],
                ]);
            }
        }
    }

    /**
     * 3. Assign Deliveries — Unassigned (10 parcels, 2 per CALABARZON province).
     * Already sorted at destination hub, awaiting manual Rider assignment.
     */
    private function seedSortedUnassignedParcels(): void
    {
        $seq = 0;
        foreach (array_keys(self::CALABARZON_PROVINCES) as $provinceCode) {
            $company = User::where('email', "logistics.{$provinceCode}@nexora.test")->firstOrFail();

            for ($k = 0; $k < 2; $k++) {
                $seq++;
                [$seller, $buyer, $hub] = $this->findSameHubSellerAndBuyer($company, $k + 2);

                $orderNo = sprintf('NX-CBZ-ASN-%02d', $seq);
                $trkNo = sprintf('NX-TRK-CBZ-ASN%02d', $seq);

                if (Order::where('order_number', $orderNo)->exists()) {
                    continue;
                }

                $order = $this->createSeededOrder($orderNo, $trkNo, $buyer, $seller, 40 + $seq);
                $order->update([
                    'status' => 'sorted',
                    'origin_hub_id' => $hub->id,
                    'destination_hub_id' => $hub->id,
                    'current_hub_id' => $hub->id,
                    'pickup_logistics_id' => $company->id,
                    'delivery_logistics_id' => $company->id,
                    'current_custody_logistics_id' => $company->id,
                    'sorting_center_id' => $company->id,
                    'pickup_courier_id' => null,
                    'pickup_rider_id' => null,
                    'delivery_courier_id' => null,
                    'delivery_rider_id' => null,
                ]);

                $this->recordStatusTrail($order, $company->id, [
                    ['placed', $buyer->id, self::FIXTURE_TAG . ' Synthetic order placed.', now()->subHours(6)],
                    ['received_at_hub', $company->id, self::FIXTURE_TAG . " Pre-positioned at {$hub->name} ({$hub->code}).", now()->subHours(4)],
                    ['sorted', $company->id, self::FIXTURE_TAG . ' Sorted for delivery zone; awaiting manual rider assignment.', now()->subHours(1)],
                ]);
            }
        }
    }

    /**
     * 4. Internal Hub-to-Hub Transfer Manifests (5 manifests x 3 parcels = 15 parcels).
     */
    private function seedInternalHubToHubManifests(): void
    {
        $routes = [
            ['NX-HUB-043400000-1', 'NX-HUB-043400000-2'], // Laguna: San Pablo -> Santa Cruz
            ['NX-HUB-043400000-3', 'NX-HUB-043400000-5'], // Laguna: Calamba -> Biñan
            ['NX-HUB-042100000-1', 'NX-HUB-042100000-2'], // Cavite: Dasmariñas -> Bacoor
            ['NX-HUB-042100000-3', 'NX-HUB-042100000-5'], // Cavite: Imus -> Tagaytay
            ['NX-HUB-041000000-1', 'NX-HUB-041000000-2'], // Batangas: Batangas City -> Lipa
        ];

        $parcelSeq = 0;
        foreach ($routes as $idx => [$originCode, $destCode]) {
            $manifestNum = $idx + 1;
            $tag = "SEED-CBZ-HTH-M{$manifestNum}";

            if (TransferManifest::where('notes', 'like', "%[{$tag}]%")->exists()) {
                $parcelSeq += 3;
                continue;
            }

            $originHub = Hub::where('code', $originCode)->where('is_active', true)->firstOrFail();
            $destHub = Hub::where('code', $destCode)->where('is_active', true)->firstOrFail();

            if ((int) $originHub->logistics_company_id !== (int) $destHub->logistics_company_id || (int) $originHub->id === (int) $destHub->id) {
                throw new RuntimeException("Invalid internal hub-to-hub route {$originCode} -> {$destCode}.");
            }

            $company = User::findOrFail($originHub->logistics_company_id);
            $orderIds = [];

            for ($p = 0; $p < 3; $p++) {
                $parcelSeq++;
                $seller = $this->findSellerForHub($originHub, $p);
                $buyer = $this->findBuyerForHubZone($destHub, $p);

                $orderNo = sprintf('NX-CBZ-HTH-%02d', $parcelSeq);
                $trkNo = sprintf('NX-TRK-CBZ-HTH%02d', $parcelSeq);

                $order = Order::where('order_number', $orderNo)->first()
                    ?? $this->createSeededOrder($orderNo, $trkNo, $buyer, $seller, 60 + $parcelSeq);

                $order->update([
                    'status' => 'received_at_hub',
                    'origin_hub_id' => $originHub->id,
                    'destination_hub_id' => $destHub->id,
                    'current_hub_id' => $originHub->id,
                    'pickup_logistics_id' => $company->id,
                    'delivery_logistics_id' => $company->id,
                    'current_custody_logistics_id' => $company->id,
                    'sorting_center_id' => $company->id,
                    'pickup_courier_id' => null,
                    'pickup_rider_id' => null,
                    'delivery_courier_id' => null,
                    'delivery_rider_id' => null,
                ]);

                if ($order->statusHistory()->count() === 0) {
                    $this->recordStatusTrail($order, $company->id, [
                        ['placed', $buyer->id, self::FIXTURE_TAG . ' Synthetic order placed.', now()->subHours(5)],
                        ['received_at_hub', $company->id, self::FIXTURE_TAG . " Staged at origin hub {$originHub->name} ({$originHub->code}) for internal transfer to {$destHub->name}.", now()->subHours(2)],
                    ]);
                }

                $orderIds[] = $order->id;
            }

            $this->manifestService->createManifest($company, [
                'origin_hub_id' => $originHub->id,
                'destination_hub_id' => $destHub->id,
                'order_ids' => $orderIds,
                'distance_km' => 28.0 + ($idx * 6),
                'notes' => "[{$tag}] " . self::FIXTURE_TAG . " Internal hub-to-hub manifest ({$originHub->name} → {$destHub->name}).",
            ]);
        }
    }

    /**
     * 5. Logistics-to-Logistics Intercompany Transfer Manifests (5 manifests x 3 parcels = 15 parcels).
     */
    private function seedInterCompanyManifests(): void
    {
        $routes = [
            ['NX-HUB-043400000-1', 'NX-HUB-042100000-1'], // Laguna (San Pablo) -> Cavite (Dasmariñas)
            ['NX-HUB-043400000-4', 'NX-HUB-041000000-3'], // Laguna (Santa Rosa) -> Batangas (Tanauan)
            ['NX-HUB-042100000-4', 'NX-HUB-045800000-1'], // Cavite (General Trias) -> Rizal (Antipolo)
            ['NX-HUB-041000000-4', 'NX-HUB-045600000-1'], // Batangas (Nasugbu) -> Quezon (Lucena)
            ['NX-HUB-045600000-1', 'NX-HUB-043400000-3'], // Quezon (Lucena) -> Laguna (Calamba)
        ];

        $parcelSeq = 0;
        foreach ($routes as $idx => [$originCode, $destCode]) {
            $manifestNum = $idx + 1;
            $tag = "SEED-CBZ-LTL-M{$manifestNum}";

            if (TransferManifest::where('notes', 'like', "%[{$tag}]%")->exists()) {
                $parcelSeq += 3;
                continue;
            }

            $originHub = Hub::where('code', $originCode)->where('is_active', true)->firstOrFail();
            $destHub = Hub::where('code', $destCode)->where('is_active', true)->firstOrFail();

            if ((int) $originHub->logistics_company_id === (int) $destHub->logistics_company_id) {
                throw new RuntimeException("Intercompany route {$originCode} -> {$destCode} must use different logistics companies.");
            }

            $senderCompany = User::findOrFail($originHub->logistics_company_id);
            $receiverCompany = User::findOrFail($destHub->logistics_company_id);
            $orderIds = [];

            for ($p = 0; $p < 3; $p++) {
                $parcelSeq++;
                $seller = $this->findSellerForHub($originHub, $p);
                $buyer = $this->findBuyerForHubZone($destHub, $p);

                $orderNo = sprintf('NX-CBZ-LTL-%02d', $parcelSeq);
                $trkNo = sprintf('NX-TRK-CBZ-LTL%02d', $parcelSeq);

                $order = Order::where('order_number', $orderNo)->first()
                    ?? $this->createSeededOrder($orderNo, $trkNo, $buyer, $seller, 80 + $parcelSeq);

                $order->update([
                    'status' => 'received_at_hub',
                    'origin_hub_id' => $originHub->id,
                    'destination_hub_id' => $destHub->id,
                    'current_hub_id' => $originHub->id,
                    'pickup_logistics_id' => $senderCompany->id,
                    'delivery_logistics_id' => $receiverCompany->id,
                    'current_custody_logistics_id' => $senderCompany->id,
                    'sorting_center_id' => $senderCompany->id,
                    'pickup_courier_id' => null,
                    'pickup_rider_id' => null,
                    'delivery_courier_id' => null,
                    'delivery_rider_id' => null,
                ]);

                if ($order->statusHistory()->count() === 0) {
                    $this->recordStatusTrail($order, $senderCompany->id, [
                        ['placed', $buyer->id, self::FIXTURE_TAG . ' Synthetic order placed.', now()->subHours(6)],
                        ['received_at_hub', $senderCompany->id, self::FIXTURE_TAG . " Staged at sender hub {$originHub->name} ({$originHub->code}) for intercompany transfer to {$destHub->name}.", now()->subHours(2)],
                    ]);
                }

                $orderIds[] = $order->id;
            }

            $this->manifestService->createManifest($senderCompany, [
                'origin_hub_id' => $originHub->id,
                'destination_hub_id' => $destHub->id,
                'order_ids' => $orderIds,
                'distance_km' => 65.0 + ($idx * 12),
                'notes' => "[{$tag}] " . self::FIXTURE_TAG . " Intercompany transfer manifest ({$originHub->name} → {$destHub->name}).",
            ]);
        }
    }

    private function createSeededOrder(string $orderNo, string $trkNo, User $buyer, User $seller, int $seedOffset): Order
    {
        return DB::transaction(function () use ($orderNo, $trkNo, $buyer, $seller, $seedOffset) {
            $buyerAddress = $buyer->address;
            if (! $buyerAddress) {
                throw new RuntimeException("Buyer {$buyer->email} has no address.");
            }

            $products = Product::where('seller_id', $seller->id)
                ->where('status', 'active')
                ->where('stock', '>', 0)
                ->orderBy('id')
                ->get();
            if ($products->isEmpty()) {
                throw new RuntimeException("Seller {$seller->email} has no active products.");
            }

            $product = $products[$seedOffset % $products->count()];
            $qty = 1 + ($seedOffset % 2);
            $unitPrice = (float) $product->price;
            $subtotal = round($unitPrice * $qty, 2);

            $quoteItems = collect([(object) ['product' => $product, 'quantity' => $qty]]);
            $quote = $this->shippingPricing->calculateSmartQuote($quoteItems, $seller->id, $buyerAddress);
            $shippingFee = (float) $quote['shipping_fee'];
            $total = round($subtotal + $shippingFee, 2);

            $order = Order::create([
                'order_number' => $orderNo,
                'tracking_number' => $trkNo,
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'address_id' => $buyerAddress->id,
                'address_snapshot' => [
                    'recipient_name' => $buyerAddress->recipient_name ?? $buyer->full_name,
                    'recipient_phone' => $buyerAddress->recipient_phone ?? $buyer->contact_number,
                    'province_code' => $buyerAddress->province_code,
                    'province_name' => $buyerAddress->province_name,
                    'municipality_code' => $buyerAddress->municipality_code,
                    'municipality_name' => $buyerAddress->municipality_name,
                    'barangay_code' => $buyerAddress->barangay_code,
                    'barangay_name' => $buyerAddress->barangay_name,
                    'street' => $buyerAddress->street,
                    'house_number' => $buyerAddress->house_number,
                    'full_address' => $buyerAddress->full_address,
                ],
                'status' => 'placed',
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'gross_merchandise_subtotal' => $subtotal,
                'discount' => 0,
                'shipping_fee' => $shippingFee,
                'base_shipping_fee' => (float) ($quote['base_shipping_fee'] ?? $shippingFee),
                'shipping_weight' => (float) ($quote['billed_weight_kg'] ?? 1.0),
                'shipping_rate_policy_id' => $quote['policy_id'] ?? null,
                'shipping_route_type' => $quote['route_type'] ?? null,
                'shipping_quote_breakdown' => $quote['breakdown'] ?? null,
                'total' => $total,
                'placed_at' => now()->subHours(6),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant' => is_array($product->variants_data) && isset($product->variants_data[0])
                    ? ['name' => $product->variants_data[0]['name'] ?? 'Standard']
                    : null,
                'price' => $unitPrice,
                'quantity' => $qty,
            ]);

            return $this->logisticsRouter->matchLogisticsAndRouteOrder($order);
        });
    }

    private function recordStatusTrail(Order $order, int $defaultActorId, array $entries): void
    {
        if ($order->statusHistory()->exists()) {
            return;
        }

        foreach ($entries as [$status, $actorId, $note, $changedAt]) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $status,
                'changed_by' => $actorId ?: $defaultActorId,
                'note' => $note,
                'changed_at' => $changedAt,
            ]);
        }
    }

    private function findSellerForHub(Hub $hub, int $offset = 0): User
    {
        $sellers = User::where('role', 'seller')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('address', fn ($q) => $q->where('province_code', $hub->province_code)->where('municipality_code', $hub->city_code))
            ->orderBy('id')
            ->get();

        if ($sellers->isEmpty()) {
            $sellers = User::where('role', 'seller')
                ->where('approval_status', 'approved')
                ->where('account_status', 'active')
                ->whereHas('address', fn ($q) => $q->where('province_code', $hub->province_code))
                ->orderBy('id')
                ->get();
        }

        if ($sellers->isEmpty()) {
            throw new RuntimeException("No approved seller found for hub {$hub->name} ({$hub->province_code}).");
        }

        return $sellers[$offset % $sellers->count()];
    }

    private function findBuyerInProvince(string $provinceCode, int $offset = 0): User
    {
        $buyers = User::where('role', 'buyer')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('address', fn ($q) => $q->where('province_code', $provinceCode))
            ->with('address')
            ->orderBy('id')
            ->get();

        if ($buyers->isEmpty()) {
            throw new RuntimeException("No approved buyer found in province {$provinceCode}.");
        }

        return $buyers[$offset % $buyers->count()];
    }

    private function findBuyerForHubZone(Hub $hub, int $offset = 0): User
    {
        $zoneCityCodes = Zone::where('hub_id', $hub->id)->where('is_active', true)->pluck('city_code')->all();

        $buyers = User::where('role', 'buyer')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('address', fn ($q) => $q->whereIn('municipality_code', $zoneCityCodes))
            ->with('address')
            ->orderBy('id')
            ->get();

        if ($buyers->isEmpty()) {
            throw new RuntimeException("No approved buyer found inside zones served by hub {$hub->name} ({$hub->code}).");
        }

        return $buyers[$offset % $buyers->count()];
    }

    private function findSameHubSellerAndBuyer(User $company, int $offset = 0): array
    {
        $hubs = Hub::where('logistics_company_id', $company->id)->where('is_active', true)->orderBy('id')->get();
        $pairs = [];

        foreach ($hubs as $hub) {
            $seller = User::where('role', 'seller')
                ->where('approval_status', 'approved')
                ->where('account_status', 'active')
                ->whereHas('address', fn ($q) => $q->where('municipality_code', $hub->city_code))
                ->orderBy('id')
                ->get();

            $zoneCityCodes = Zone::where('hub_id', $hub->id)->where('is_active', true)->pluck('city_code')->all();
            $buyers = User::where('role', 'buyer')
                ->where('approval_status', 'approved')
                ->where('account_status', 'active')
                ->whereHas('address', fn ($q) => $q->whereIn('municipality_code', $zoneCityCodes))
                ->with('address')
                ->orderBy('id')
                ->get();

            if ($seller->isNotEmpty() && $buyers->isNotEmpty()) {
                foreach ($seller as $sIdx => $s) {
                    $b = $buyers[$sIdx % $buyers->count()];
                    $pairs[] = [$s, $b, $hub];
                }
            }
        }

        if (empty($pairs)) {
            throw new RuntimeException("No same-hub seller/buyer pair found for company {$company->email}.");
        }

        return $pairs[$offset % count($pairs)];
    }

    private function assertWorkflowDataset(): void
    {
        $counts = [
            'pickups' => Order::where('order_number', 'like', 'NX-CBZ-PKP-%')->where('status', 'ready_for_pickup')->whereNull('pickup_courier_id')->count(),
            'unsorted' => Order::where('order_number', 'like', 'NX-CBZ-SRT-%')->where('status', 'received_at_hub')->whereColumn('current_hub_id', 'destination_hub_id')->whereNull('delivery_courier_id')->count(),
            'sorted' => Order::where('order_number', 'like', 'NX-CBZ-ASN-%')->where('status', 'sorted')->whereColumn('current_hub_id', 'destination_hub_id')->whereNull('delivery_courier_id')->count(),
            'hth_manifests' => TransferManifest::where('notes', 'like', '%SEED-CBZ-HTH-M%')->where('status', TransferManifest::STATUS_DRAFT)->whereNull('assigned_courier_id')->count(),
            'ltl_manifests' => TransferManifest::where('notes', 'like', '%SEED-CBZ-LTL-M%')->where('status', TransferManifest::STATUS_DRAFT)->whereNull('assigned_courier_id')->count(),
            'hth_orders' => Order::where('order_number', 'like', 'NX-CBZ-HTH-%')->count(),
            'ltl_orders' => Order::where('order_number', 'like', 'NX-CBZ-LTL-%')->count(),
        ];

        $expected = [
            'pickups' => 10,
            'unsorted' => 10,
            'sorted' => 10,
            'hth_manifests' => 5,
            'ltl_manifests' => 5,
            'hth_orders' => 15,
            'ltl_orders' => 15,
        ];

        foreach ($expected as $key => $val) {
            if ($counts[$key] !== $val) {
                throw new RuntimeException("Workflow count check failed for {$key}: expected {$val}, got {$counts[$key]}.");
            }
        }

        foreach (self::FINANCIAL_POD_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $before = $this->preFinancialCounts[$table] ?? 0;
            $after = DB::table($table)->count();
            if ($after !== $before) {
                throw new RuntimeException("Financial/POD table {$table} changed during workflow seeding ({$before} -> {$after}).");
            }
        }

        $seededOrderIds = Order::where('order_number', 'like', 'NX-CBZ-%')->pluck('id');
        if ($seededOrderIds->isNotEmpty()) {
            foreach (['rider_earnings', 'cod_collections', 'commissions', 'proof_of_deliveries'] as $orderLinkedTable) {
                if (Schema::hasTable($orderLinkedTable) && Schema::hasColumn($orderLinkedTable, 'order_id')) {
                    $linkedCount = DB::table($orderLinkedTable)->whereIn('order_id', $seededOrderIds)->count();
                    if ($linkedCount !== 0) {
                        throw new RuntimeException("Seeded workflow orders must have zero rows in {$orderLinkedTable}, found {$linkedCount}.");
                    }
                }
            }

            $assignedSeeded = Order::whereIn('id', $seededOrderIds)
                ->where(function ($q) {
                    $q->whereNotNull('pickup_courier_id')
                        ->orWhereNotNull('pickup_rider_id')
                        ->orWhereNotNull('delivery_courier_id')
                        ->orWhereNotNull('delivery_rider_id');
                })
                ->count();
            if ($assignedSeeded !== 0) {
                throw new RuntimeException("Seeded workflow orders must have zero assigned riders/couriers, found {$assignedSeeded}.");
            }
        }

        $this->command?->info('CalabarzonLogisticsWorkflowSeeder complete: 60 CALABARZON parcels (10 pickups, 10 unsorted, 10 sorted, 15 hub-to-hub, 15 intercompany) & 10 draft manifests.');
    }
}

