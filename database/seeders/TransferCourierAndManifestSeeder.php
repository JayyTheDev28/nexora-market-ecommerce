<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\OrderLogisticsService;
use App\Services\TransferManifestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransferCourierAndManifestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear all previously seeded transfer courier / manifest / parcel records
        DB::transaction(function () {
            $oldManifestIds = TransferManifest::where('notes', 'like', 'Seeded%')->pluck('id');
            $manifestOrderIds = TransferManifestItem::whereIn('manifest_id', $oldManifestIds)->pluck('order_id');
            if ($oldManifestIds->isNotEmpty()) {
                TransferManifestItem::whereIn('manifest_id', $oldManifestIds)->delete();
                HubTransfer::whereIn('manifest_id', $oldManifestIds)->delete();
                TransferManifest::whereIn('id', $oldManifestIds)->delete();
            }

            $historyOrderIds = OrderStatusHistory::where('note', 'like', 'Checked in at %')->pluck('order_id');
            $oldOrderIds = Order::whereIn('tracking_number', [
                'NX-TRK-1001', 'NX-TRK-1002', 'NX-TRK-1003',
                'NX-TRK-2001', 'NX-TRK-2002', 'NX-TRK-3001', 'NX-TRK-3002',
            ])->orWhereIn('id', $manifestOrderIds)
              ->orWhereIn('id', $historyOrderIds)
              ->orWhere(fn ($q) => $q->where('status', 'received_at_hub')->whereNull('tracking_number'))
              ->pluck('id');

            if ($oldOrderIds->isNotEmpty()) {
                HubTransfer::whereIn('order_id', $oldOrderIds)->delete();
                OrderItem::whereIn('order_id', $oldOrderIds)->delete();
                OrderStatusHistory::whereIn('order_id', $oldOrderIds)->delete();
                Order::whereIn('id', $oldOrderIds)->delete();
            }

            $oldCourier = User::where('email', 'courier@nexora.test')->first();
            if ($oldCourier) {
                CourierProfile::where('user_id', $oldCourier->id)->delete();
                $oldCourier->delete();
            }
        });

        $logistics = User::where('email', 'logistics@nexora.test')->first()
            ?? User::where('role', 'logistics')->firstOrFail();

        // Origin = Calamba Hub, Destination = Sta Cruz Hub
        $calambaHub = Hub::where('code', 'HUB-LAG-CAL')->firstOrFail();
        $staCruzHub = Hub::where('code', 'HUB-LAG-SC')->firstOrFail();

        // 2. Seed Transfer Courier assigned to Calamba Hub
        $courier = User::create([
            'first_name' => 'Marco',
            'last_name' => 'Villanueva',
            'email' => 'courier@nexora.test',
            'contact_number' => '09178889900',
            'birthday' => '1993-04-12',
            'age' => 33,
            'sex' => 'male',
            'password' => Hash::make('Password123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $courier->id,
            'sorting_center_id' => $logistics->id,
            'logistics_company_id' => $logistics->id,
            'assigned_hub_id' => $calambaHub->id,
            'vehicle_type' => null,
            'plate_number' => null,
            'license_number' => 'N02-19-884210',
            'license_expiry_date' => now()->addYears(2),
        ]);

        // Buyer address in Santa Cruz, Laguna (destination hub = Sta Cruz Hub)
        $buyer = User::where('role', 'buyer')->firstOrFail();
        $staCruzAddress = Address::updateOrCreate(
            [
                'user_id' => $buyer->id,
                'municipality_code' => '043426000',
                'barangay_code' => '043426001',
            ],
            [
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_name' => 'Santa Cruz',
                'barangay_name' => 'Poblacion I',
                'street' => '45 P. Guevara Ave, Santa Cruz',
                'recipient_name' => $buyer->full_name ?: 'Juan Dela Cruz',
                'recipient_phone' => '09170000002',
                'is_default' => false,
            ]
        );

        $products = Product::orderBy('id')->take(5)->get();
        abort_if($products->isEmpty(), 500, 'No products found to seed orders.');

        $logisticsOrderService = app(OrderLogisticsService::class);
        $manifestService = app(TransferManifestService::class);

        // 3. Create 20 parcels received at Calamba Hub -> destined for Sta Cruz Hub
        $orders = [];
        for ($i = 0; $i < 20; $i++) {
            $product = $products[$i % $products->count()];
            $subtotal = (float) $product->price;
            $shippingFee = 50.00;

            $order = Order::create([
                'order_number' => 'NX-' . strtoupper(Str::random(10)),
                'buyer_id' => $buyer->id,
                'seller_id' => $product->seller_id,
                'address_id' => $staCruzAddress->id,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total' => $subtotal + $shippingFee,
                'payment_method' => 'cod',
                'status' => 'received_at_hub',
                'pickup_logistics_id' => $logistics->id,
                'sorting_center_id' => $logistics->id,
                'current_custody_logistics_id' => $logistics->id,
                'origin_hub_id' => $calambaHub->id,
                'current_hub_id' => $calambaHub->id,
                'destination_hub_id' => $staCruzHub->id,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 1,
                'price' => $product->price,
            ]);

            $logisticsOrderService->ensureTrackingNumber($order);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'received_at_hub',
                'note' => "Checked in at {$calambaHub->name}",
                'changed_by' => $logistics->id,
                'changed_at' => now(),
            ]);

            $orders[] = $order->fresh();
        }

        // 4. Assign Transfer Manifest: Calamba Hub -> Sta Cruz Hub (20 parcels)
        $manifest = $manifestService->createManifest($logistics, [
            'origin_hub_id' => $calambaHub->id,
            'destination_hub_id' => $staCruzHub->id,
            'order_ids' => collect($orders)->pluck('id')->all(),
            'assigned_courier_id' => $courier->id,
            'vehicle_plate_number' => 'NXA 8801',
            'notes' => 'Seeded 20-parcel transfer: Calamba Hub -> Sta Cruz Hub',
        ]);

        // 5. Load all 20 parcels, depart, arrive at Sta Cruz Hub, receive 19, mark 1 missing
        foreach ($orders as $order) {
            $manifestService->scanLoadingParcel($manifest, $order->tracking_number, $courier);
        }

        $manifestService->confirmDeparture($manifest, $courier, 'NXA 8801');
        $manifestService->markArrived($manifest, $courier);

        foreach ($orders as $index => $order) {
            if ($index === 19) {
                $manifestService->verifyReceivingParcel(
                    $manifest,
                    $order->tracking_number,
                    TransferManifestItem::RECEIVING_MISSING,
                    $logistics,
                    'Parcel missing during unloading at Sta Cruz Hub'
                );
            } else {
                $manifestService->verifyReceivingParcel(
                    $manifest,
                    $order->tracking_number,
                    TransferManifestItem::RECEIVING_RECEIVED,
                    $logistics
                );
            }
        }
    }
}
