<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add immutable financial breakdown snapshot columns to orders
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'gross_merchandise_subtotal')) {
                $table->decimal('gross_merchandise_subtotal', 12, 2)->nullable()->after('subtotal');
            }
            if (! Schema::hasColumn('orders', 'base_shipping_fee')) {
                $table->decimal('base_shipping_fee', 12, 2)->nullable()->after('shipping_fee');
            }
            if (! Schema::hasColumn('orders', 'store_voucher_discount')) {
                $table->decimal('store_voucher_discount', 12, 2)->default(0.00)->after('discount');
            }
            if (! Schema::hasColumn('orders', 'platform_voucher_discount')) {
                $table->decimal('platform_voucher_discount', 12, 2)->default(0.00)->after('store_voucher_discount');
            }
            if (! Schema::hasColumn('orders', 'shipping_voucher_discount')) {
                $table->decimal('shipping_voucher_discount', 12, 2)->default(0.00)->after('platform_voucher_discount');
            }
            if (! Schema::hasColumn('orders', 'platform_shipping_subsidy')) {
                $table->decimal('platform_shipping_subsidy', 12, 2)->default(0.00)->after('shipping_voucher_discount');
            }
            if (! Schema::hasColumn('orders', 'shipping_rate_policy_id')) {
                $table->unsignedBigInteger('shipping_rate_policy_id')->nullable()->after('shipping_voucher_id');
            }
        });

        // 2. Per-Order Immutable COD Collection Accountability Table
        Schema::create('cod_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collecting_logistics_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->foreignId('proof_of_delivery_id')->nullable()->constrained('proof_of_deliveries')->nullOnDelete();
            $table->decimal('cod_amount_due', 12, 2);
            $table->decimal('actual_collected_amount', 12, 2);
            $table->decimal('verified_remitted_amount', 12, 2)->default(0.00);
            $table->decimal('outstanding_amount', 12, 2)->default(0.00);
            $table->decimal('shortage_amount', 12, 2)->default(0.00);
            $table->decimal('overpayment_amount', 12, 2)->default(0.00);
            $table->string('status', 40)->default('collected'); // collected, submitted_for_remittance, partially_remitted, verified, disputed, shortage_flagged, reversed
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status'], 'cc_courier_status_idx');
            $table->index(['collecting_logistics_id', 'status'], 'cc_logistics_status_idx');
            $table->index('collected_at', 'cc_collected_at_idx');
        });

        // 3. Extend cod_remittances for physical cash & verified manual digital remittance channels
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cod_remittances MODIFY status VARCHAR(40) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('cod_remittances', function (Blueprint $table) {
            if (! Schema::hasColumn('cod_remittances', 'collecting_hub_id')) {
                $table->foreignId('collecting_hub_id')->nullable()->after('sorting_center_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('cod_remittances', 'claimed_amount')) {
                $table->decimal('claimed_amount', 12, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('cod_remittances', 'actual_received_amount')) {
                $table->decimal('actual_received_amount', 12, 2)->nullable()->after('claimed_amount');
            }
            if (! Schema::hasColumn('cod_remittances', 'shortage_amount')) {
                $table->decimal('shortage_amount', 12, 2)->default(0.00)->after('actual_received_amount');
            }
            if (! Schema::hasColumn('cod_remittances', 'overpayment_amount')) {
                $table->decimal('overpayment_amount', 12, 2)->default(0.00)->after('shortage_amount');
            }
            if (! Schema::hasColumn('cod_remittances', 'payment_method')) {
                $table->string('payment_method', 40)->default('physical_cash')->after('expected_amount');
            }
            if (! Schema::hasColumn('cod_remittances', 'external_reference')) {
                $table->string('external_reference', 120)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('cod_remittances', 'transfer_date')) {
                $table->timestamp('transfer_date')->nullable()->after('external_reference');
            }
            if (! Schema::hasColumn('cod_remittances', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('transfer_date');
            }
            if (! Schema::hasColumn('cod_remittances', 'verification_reference')) {
                $table->string('verification_reference', 120)->nullable()->after('proof_path');
            }
            if (! Schema::hasColumn('cod_remittances', 'discrepancy_reason')) {
                $table->text('discrepancy_reason')->nullable()->after('operator_notes');
            }
            if (! Schema::hasColumn('cod_remittances', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->unique()->after('reviewed_at');
            }
        });

        // 4. Itemized allocations linking a Rider COD remittance batch to specific COD collection orders
        Schema::create('cod_remittance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_remittance_id')->constrained('cod_remittances')->cascadeOnDelete();
            $table->foreignId('cod_collection_id')->constrained('cod_collections')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->decimal('verified_amount', 12, 2)->default(0.00);
            $table->decimal('shortage_amount', 12, 2)->default(0.00);
            $table->decimal('overpayment_amount', 12, 2)->default(0.00);
            $table->string('status', 40)->default('submitted'); // submitted, verified, partially_verified, disputed, rejected, released
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['cod_remittance_id', 'cod_collection_id'], 'cra_remit_collection_unique');
            $table->index(['order_id', 'status'], 'cra_order_status_idx');
        });

        // 5. Extend cod_ledger_entries so adjustments, shortages, overpayments, reversals are append-only
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cod_ledger_entries MODIFY type VARCHAR(40) NOT NULL DEFAULT 'collected'");
        }

        Schema::table('cod_ledger_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('cod_ledger_entries', 'cod_remittance_id')) {
                $table->foreignId('cod_remittance_id')->nullable()->after('order_id')->constrained('cod_remittances')->nullOnDelete();
            }
            if (! Schema::hasColumn('cod_ledger_entries', 'cod_collection_id')) {
                $table->foreignId('cod_collection_id')->nullable()->after('cod_remittance_id')->constrained('cod_collections')->nullOnDelete();
            }
        });

        // 6. Extend logistics_admin_remittances and logistics_remittance_items
        Schema::table('logistics_admin_remittances', function (Blueprint $table) {
            if (! Schema::hasColumn('logistics_admin_remittances', 'actual_received_amount')) {
                $table->decimal('actual_received_amount', 12, 2)->nullable()->after('merchandise_amount');
            }
            if (! Schema::hasColumn('logistics_admin_remittances', 'variance_amount')) {
                $table->decimal('variance_amount', 12, 2)->default(0.00)->after('actual_received_amount');
            }
            if (! Schema::hasColumn('logistics_admin_remittances', 'verified_bank_reference')) {
                $table->string('verified_bank_reference', 120)->nullable()->after('transfer_reference');
            }
            if (! Schema::hasColumn('logistics_admin_remittances', 'discrepancy_notes')) {
                $table->text('discrepancy_notes')->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('logistics_admin_remittances', 'shipping_subsidy_total')) {
                $table->decimal('shipping_subsidy_total', 12, 2)->default(0.00)->after('shipping_retained');
            }
        });

        Schema::table('logistics_remittance_items', function (Blueprint $table) {
            if (! Schema::hasColumn('logistics_remittance_items', 'cod_collection_id')) {
                $table->foreignId('cod_collection_id')->nullable()->after('cod_remittance_id')->constrained('cod_collections')->nullOnDelete();
            }
            if (! Schema::hasColumn('logistics_remittance_items', 'seller_discount_amount')) {
                $table->decimal('seller_discount_amount', 12, 2)->default(0.00)->after('merchandise_component');
            }
            if (! Schema::hasColumn('logistics_remittance_items', 'platform_discount_amount')) {
                $table->decimal('platform_discount_amount', 12, 2)->default(0.00)->after('seller_discount_amount');
            }
            if (! Schema::hasColumn('logistics_remittance_items', 'platform_shipping_subsidy')) {
                $table->decimal('platform_shipping_subsidy', 12, 2)->default(0.00)->after('platform_discount_amount');
            }
        });

        // 7. Financial adjustments (refunds, post-settlement reversals, dispute holds, write-offs)
        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 60)->unique();
            $table->foreignId('logistics_company_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_type', 50); // cod_shortage_writeoff, cod_overpayment_refund, post_settlement_reversal, dispute_hold, earnings_adjustment
            $table->string('pool', 40); // cod_cash, courier_earnings, logistics_shipping, admin_merchandise, expense
            $table->decimal('amount', 12, 2); // positive or negative signed delta
            $table->string('status', 30)->default('applied'); // pending, applied, reversed
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['logistics_company_id', 'pool'], 'fa_logistics_pool_idx');
            $table->index(['order_id', 'status'], 'fa_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_adjustments');

        Schema::table('logistics_remittance_items', function (Blueprint $table) {
            $table->dropForeign(['cod_collection_id']);
            $table->dropColumn([
                'cod_collection_id',
                'seller_discount_amount',
                'platform_discount_amount',
                'platform_shipping_subsidy',
            ]);
        });

        Schema::table('logistics_admin_remittances', function (Blueprint $table) {
            $table->dropColumn([
                'actual_received_amount',
                'variance_amount',
                'verified_bank_reference',
                'discrepancy_notes',
                'shipping_subsidy_total',
            ]);
        });

        Schema::table('cod_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['cod_remittance_id']);
            $table->dropForeign(['cod_collection_id']);
            $table->dropColumn(['cod_remittance_id', 'cod_collection_id']);
        });

        Schema::dropIfExists('cod_remittance_allocations');

        Schema::table('cod_remittances', function (Blueprint $table) {
            $table->dropForeign(['collecting_hub_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'collecting_hub_id',
                'claimed_amount',
                'actual_received_amount',
                'shortage_amount',
                'overpayment_amount',
                'payment_method',
                'external_reference',
                'transfer_date',
                'proof_path',
                'verification_reference',
                'discrepancy_reason',
                'idempotency_key',
            ]);
        });

        Schema::dropIfExists('cod_collections');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'gross_merchandise_subtotal',
                'base_shipping_fee',
                'store_voucher_discount',
                'platform_voucher_discount',
                'shipping_voucher_discount',
                'platform_shipping_subsidy',
                'shipping_rate_policy_id',
            ]);
        });
    }
};
