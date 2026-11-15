<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Versioned Compensation Policies (Rider & Transfer Courier)
        Schema::create('compensation_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 30); // rider, courier
            $table->string('name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft'); // draft, proposed, approved, active, archived
            $table->decimal('pickup_rate', 10, 2)->default(30.00);
            $table->decimal('delivery_rate', 10, 2)->default(50.00);
            $table->decimal('failed_attempt_rate', 10, 2)->default(0.00);
            $table->decimal('transfer_trip_base_rate', 10, 2)->default(150.00);
            $table->decimal('transfer_per_km_rate', 10, 2)->default(0.00);
            $table->decimal('transfer_per_parcel_rate', 10, 2)->default(0.00);
            $table->decimal('transfer_per_kg_rate', 10, 2)->default(0.00);
            $table->decimal('transfer_fuel_allowance', 10, 2)->default(0.00);
            $table->decimal('transfer_exception_rate', 10, 2)->default(0.00);
            $table->boolean('pay_on_exception')->default(false);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['logistics_company_id', 'role', 'status'], 'cp_company_role_status_idx');
        });

        // 2. Manual Verified Courier Payouts (Rider & Transfer Courier withdrawals)
        Schema::create('courier_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_number', 60)->unique();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logistics_company_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 30)->default('rider'); // rider, courier
            $table->decimal('amount', 12, 2);
            $table->string('payout_method', 40); // physical_cash, gcash, maya, bank_transfer
            $table->string('account_name', 120);
            $table->string('account_number', 120);
            $table->string('status', 30)->default('requested'); // requested, under_review, approved, paid, rejected, cancelled
            $table->string('external_reference', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->text('courier_notes')->nullable();
            $table->text('operator_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamps();

            $table->index(['courier_id', 'status'], 'cpay_courier_status_idx');
            $table->index(['logistics_company_id', 'status'], 'cpay_logistics_status_idx');
        });

        // 3. Extend rider_earnings cleanly for rate locking, transfer manifests, status, and payouts
        Schema::table('rider_earnings', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rider_earnings MODIFY order_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE rider_earnings MODIFY type VARCHAR(40) NOT NULL DEFAULT 'pickup'");
        } else {
            Schema::table('rider_earnings', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->change();
                $table->string('type', 40)->default('pickup')->change();
            });
        }

        Schema::table('rider_earnings', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            if (! Schema::hasColumn('rider_earnings', 'logistics_company_id')) {
                $table->foreignId('logistics_company_id')->nullable()->after('courier_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('rider_earnings', 'manifest_id')) {
                $table->foreignId('manifest_id')->nullable()->after('order_id')->constrained('transfer_manifests')->nullOnDelete();
            }
            if (! Schema::hasColumn('rider_earnings', 'policy_id')) {
                $table->foreignId('policy_id')->nullable()->after('manifest_id')->constrained('compensation_policies')->nullOnDelete();
            }
            if (! Schema::hasColumn('rider_earnings', 'courier_payout_id')) {
                $table->foreignId('courier_payout_id')->nullable()->after('policy_id')->constrained('courier_payouts')->nullOnDelete();
            }
            if (! Schema::hasColumn('rider_earnings', 'earning_key')) {
                $table->string('earning_key', 120)->nullable()->unique()->after('courier_payout_id');
            }
            if (! Schema::hasColumn('rider_earnings', 'status')) {
                $table->string('status', 40)->default('earned')->after('amount'); // pending, earned, approved_for_payout, paid, on_hold, disputed, reversed
            }
            if (! Schema::hasColumn('rider_earnings', 'rate_snapshot')) {
                $table->json('rate_snapshot')->nullable()->after('description');
            }

            $table->index(['courier_id', 'status'], 're_courier_status_idx');
        });

        // 4. Payout Items linking each paid earning uniquely to a single payout
        Schema::create('courier_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_payout_id')->constrained('courier_payouts')->cascadeOnDelete();
            $table->foreignId('rider_earning_id')->unique()->constrained('rider_earnings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // 5. Add optional distance_km on transfer_manifests for route compensation & profitability
        Schema::table('transfer_manifests', function (Blueprint $table) {
            if (! Schema::hasColumn('transfer_manifests', 'distance_km')) {
                $table->decimal('distance_km', 8, 2)->nullable()->after('total_parcels');
            }
        });

        // 6. Configurable Shipping Rate & Inter-Company Revenue Split Policies
        Schema::create('shipping_rate_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('logistics_company_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft'); // draft, active, archived
            $table->decimal('base_fee', 10, 2)->default(50.00);
            $table->decimal('base_weight_kg', 8, 2)->default(1.00);
            $table->decimal('per_additional_kg_fee', 10, 2)->default(20.00);
            $table->decimal('minimum_fee', 10, 2)->default(50.00);
            $table->decimal('origin_logistics_share_pct', 5, 2)->default(35.00);
            $table->decimal('transfer_carrier_share_pct', 5, 2)->default(20.00);
            $table->decimal('delivery_logistics_share_pct', 5, 2)->default(45.00);
            $table->decimal('platform_shipping_commission_pct', 5, 2)->default(0.00);
            $table->timestamp('effective_from')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['logistics_company_id', 'status'], 'srp_company_status_idx');
        });

        // 7. Inter-Company Shipping Revenue Allocations per Order
        Schema::create('inter_company_shipping_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('shipping_rate_policy_id')->nullable();
            $table->decimal('total_shipping_fee', 12, 2);
            $table->decimal('buyer_paid_shipping', 12, 2);
            $table->decimal('platform_shipping_subsidy', 12, 2)->default(0.00);
            $table->unsignedBigInteger('pickup_logistics_id')->nullable();
            $table->decimal('pickup_share_amount', 12, 2)->default(0.00);
            $table->unsignedBigInteger('transfer_logistics_id')->nullable();
            $table->decimal('transfer_share_amount', 12, 2)->default(0.00);
            $table->unsignedBigInteger('delivery_logistics_id')->nullable();
            $table->decimal('delivery_share_amount', 12, 2)->default(0.00);
            $table->unsignedBigInteger('collecting_logistics_id')->nullable();
            $table->string('settlement_status', 30)->default('allocated'); // pending, allocated, settled, disputed
            $table->timestamp('settled_at')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->foreign('order_id', 'icsa_order_fk')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('shipping_rate_policy_id', 'icsa_rate_policy_fk')->references('id')->on('shipping_rate_policies')->nullOnDelete();
            $table->foreign('pickup_logistics_id', 'icsa_pickup_log_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('transfer_logistics_id', 'icsa_transfer_log_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('delivery_logistics_id', 'icsa_delivery_log_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('collecting_logistics_id', 'icsa_collecting_log_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['collecting_logistics_id', 'settlement_status'], 'icsa_collecting_status_idx');
            $table->index(['pickup_logistics_id', 'settlement_status'], 'icsa_pickup_status_idx');
            $table->index(['delivery_logistics_id', 'settlement_status'], 'icsa_delivery_status_idx');
        });

        // 8. Company-Scoped Real Logistics Expenses
        Schema::create('logistics_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 60)->unique();
            $table->foreignId('logistics_company_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->foreignId('manifest_id')->nullable()->constrained('transfer_manifests')->nullOnDelete();
            $table->string('category', 50); // fuel, vehicle_maintenance, hub_rent, utilities, packaging, staff_wages, tolls, other
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('reference_number', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('recorded'); // recorded, voided
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['logistics_company_id', 'expense_date'], 'le_company_date_idx');
            $table->index(['logistics_company_id', 'category'], 'le_company_category_idx');
        });

        // 9. Seed baseline active defaults (₱30/₱50 Rider, ₱150 Transfer Trip, ₱50+₱20/kg Shipping)
        // plus Draft/Proposed ₱10/₱30 Rider policy for explicit operator review.
        $now = now();
        DB::table('compensation_policies')->insert([
            [
                'logistics_company_id' => null,
                'role' => 'rider',
                'name' => 'Standard Rider Compensation Policy (Legacy Active Baseline)',
                'version' => 1,
                'status' => 'active',
                'pickup_rate' => 30.00,
                'delivery_rate' => 50.00,
                'failed_attempt_rate' => 0.00,
                'transfer_trip_base_rate' => 0.00,
                'transfer_per_km_rate' => 0.00,
                'transfer_per_parcel_rate' => 0.00,
                'transfer_per_kg_rate' => 0.00,
                'transfer_fuel_allowance' => 0.00,
                'transfer_exception_rate' => 0.00,
                'pay_on_exception' => false,
                'effective_from' => $now,
                'notes' => 'Active baseline preserving existing ₱30 pickup / ₱50 delivery rates.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'logistics_company_id' => null,
                'role' => 'rider',
                'name' => 'Proposed Revised Rider Rate Schedule (₱10 Pickup / ₱30 Delivery)',
                'version' => 2,
                'status' => 'draft',
                'pickup_rate' => 10.00,
                'delivery_rate' => 30.00,
                'failed_attempt_rate' => 0.00,
                'transfer_trip_base_rate' => 0.00,
                'transfer_per_km_rate' => 0.00,
                'transfer_per_parcel_rate' => 0.00,
                'transfer_per_kg_rate' => 0.00,
                'transfer_fuel_allowance' => 0.00,
                'transfer_exception_rate' => 0.00,
                'pay_on_exception' => false,
                'effective_from' => null,
                'notes' => 'Proposed schedule requiring explicit Logistics/Admin approval before activation.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'logistics_company_id' => null,
                'role' => 'courier',
                'name' => 'Standard Transfer Courier Trip Policy',
                'version' => 1,
                'status' => 'active',
                'pickup_rate' => 0.00,
                'delivery_rate' => 0.00,
                'failed_attempt_rate' => 0.00,
                'transfer_trip_base_rate' => 150.00,
                'transfer_per_km_rate' => 2.50,
                'transfer_per_parcel_rate' => 5.00,
                'transfer_per_kg_rate' => 0.00,
                'transfer_fuel_allowance' => 50.00,
                'transfer_exception_rate' => 75.00,
                'pay_on_exception' => false,
                'effective_from' => $now,
                'notes' => 'Default Transfer Courier trip compensation applied upon completed hub transfer.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('shipping_rate_policies')->insert([
            'name' => 'Standard Weight-Tiered Shipping Rate Policy',
            'logistics_company_id' => null,
            'version' => 1,
            'status' => 'active',
            'base_fee' => 50.00,
            'base_weight_kg' => 1.00,
            'per_additional_kg_fee' => 20.00,
            'minimum_fee' => 50.00,
            'origin_logistics_share_pct' => 35.00,
            'transfer_carrier_share_pct' => 20.00,
            'delivery_logistics_share_pct' => 45.00,
            'platform_shipping_commission_pct' => 0.00,
            'effective_from' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_expenses');
        Schema::dropIfExists('inter_company_shipping_allocations');
        Schema::dropIfExists('shipping_rate_policies');

        Schema::table('transfer_manifests', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_manifests', 'distance_km')) {
                $table->dropColumn('distance_km');
            }
        });

        Schema::dropIfExists('courier_payout_items');

        Schema::table('rider_earnings', function (Blueprint $table) {
            $table->dropIndex('re_courier_status_idx');
            $table->dropForeign(['logistics_company_id']);
            $table->dropForeign(['manifest_id']);
            $table->dropForeign(['policy_id']);
            $table->dropForeign(['courier_payout_id']);
            $table->dropUnique(['earning_key']);
            $table->dropColumn([
                'logistics_company_id',
                'manifest_id',
                'policy_id',
                'courier_payout_id',
                'earning_key',
                'status',
                'rate_snapshot',
            ]);
        });

        Schema::dropIfExists('courier_payouts');
        Schema::dropIfExists('compensation_policies');
    }
};
