<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complete COD Remittance (Logistics -> Admin) and Seller Settlement System.
     */
    public function up(): void
    {
        // 1. Logistics to Admin Remittances
        Schema::create('logistics_admin_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference_number', 50)->unique();
            $table->decimal('total_cod_amount', 12, 2)->default(0.00);
            $table->decimal('shipping_retained', 12, 2)->default(0.00);
            $table->decimal('merchandise_amount', 12, 2)->default(0.00);
            $table->string('transfer_method', 50)->default('bank_transfer'); // bank_transfer, gcash, maya, cash_deposit, check
            $table->string('transfer_reference', 100);
            $table->string('proof_path');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('submitted'); // draft, submitted, under_review, verified, rejected, disputed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['logistics_id', 'status'], 'lar_logistics_status_idx');
            $table->index('reference_number', 'lar_reference_idx');
        });

        // 2. Remittance Items (Itemized orders in a logistics remittance batch)
        Schema::create('logistics_remittance_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_admin_remittance_id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('cod_remittance_id')->nullable()->constrained('cod_remittances')->nullOnDelete();
            $table->decimal('total_cod_amount', 12, 2)->default(0.00);
            $table->decimal('shipping_component', 12, 2)->default(0.00);
            $table->decimal('merchandise_component', 12, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('logistics_admin_remittance_id', 'lri_admin_remit_fk')
                ->references('id')
                ->on('logistics_admin_remittances')
                ->cascadeOnDelete();

            $table->unique(['logistics_admin_remittance_id', 'order_id'], 'remit_item_order_unique');
            $table->index('order_id', 'lri_order_idx');
        });

        // 3. Seller Settlement Allocations (Traceable per order, seller, and remittance)
        Schema::create('seller_settlement_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('logistics_admin_remittance_id')->nullable();
            $table->decimal('eligible_merchandise_amount', 12, 2)->default(0.00);
            $table->decimal('commission_rate', 5, 4)->default(0.1000); // 10%
            $table->decimal('platform_commission', 12, 2)->default(0.00);
            $table->decimal('seller_payable', 12, 2)->default(0.00);
            $table->string('status', 30)->default('pending'); // pending, available, payout_requested, paid
            $table->unsignedBigInteger('seller_payout_request_id')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->foreign('logistics_admin_remittance_id', 'ssa_admin_remit_fk')
                ->references('id')
                ->on('logistics_admin_remittances')
                ->nullOnDelete();

            $table->foreign('seller_payout_request_id', 'ssa_payout_req_fk')
                ->references('id')
                ->on('seller_payout_requests')
                ->nullOnDelete();

            $table->index(['seller_id', 'status'], 'ssa_seller_status_idx');
            $table->index('logistics_admin_remittance_id', 'ssa_admin_remit_idx');
        });

        // 4. Extend Seller Payout Requests with audit and disbursement reference
        Schema::table('seller_payout_requests', function (Blueprint $table) {
            $table->string('reference_number', 50)->nullable()->after('id');
            $table->string('transaction_reference', 100)->nullable()->after('account_number');
            $table->string('proof_path')->nullable()->after('transaction_reference');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('admin_notes');
            $table->timestamp('disbursed_at')->nullable()->after('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_payout_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'reference_number',
                'transaction_reference',
                'proof_path',
                'reviewed_by',
                'rejection_reason',
                'disbursed_at',
            ]);
        });

        Schema::dropIfExists('seller_settlement_allocations');
        Schema::dropIfExists('logistics_remittance_items');
        Schema::dropIfExists('logistics_admin_remittances');
    }
};
