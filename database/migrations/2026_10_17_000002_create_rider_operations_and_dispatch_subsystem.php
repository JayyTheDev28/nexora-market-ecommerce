<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance courier_profiles with availability, balances, and vehicle re-verification
        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('sorting_center_id');
            $table->decimal('earnings_balance', 10, 2)->default(0.00)->after('is_online');
            $table->decimal('unremitted_cod_balance', 10, 2)->default(0.00)->after('earnings_balance');
            $table->string('vehicle_verification_status', 20)->default('verified')->after('license_path');
            $table->string('pending_vehicle_type', 50)->nullable()->after('vehicle_verification_status');
            $table->string('pending_plate_number', 50)->nullable()->after('pending_vehicle_type');
            $table->string('pending_or_cr_path')->nullable()->after('pending_plate_number');
        });

        // 2. Enhance orders with delivery OTP and brute-force lock protection
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_otp', 6)->nullable()->after('status');
            $table->unsignedTinyInteger('delivery_otp_failed_attempts')->default(0)->after('delivery_otp');
            $table->timestamp('delivery_otp_locked_until')->nullable()->after('delivery_otp_failed_attempts');
        });

        // 3. Job Offers (60s SLA engine)
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['pickup', 'delivery'])->default('pickup');
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->decimal('cod_amount', 10, 2)->default(0.00);
            $table->decimal('expected_pay', 10, 2)->default(0.00);
            $table->timestamp('expires_at');
            $table->unsignedInteger('consecutive_declines_count')->default(0);
            $table->timestamps();

            $table->index(['courier_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        // 4. Proof of Delivery (POD)
        Schema::create('proof_of_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('verified_otp', 6);
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at');
            $table->timestamps();
        });

        // 5. Rider Earnings Ledger (₱30 pickup, ₱50 delivery, ₱0 failed)
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('type', ['pickup', 'delivery', 'bonus'])->default('pickup');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('description');
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->index(['courier_id', 'earned_at']);
        });

        // 6. COD Ledger Entries
        Schema::create('cod_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('type', ['collected', 'remitted'])->default('collected');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description');
            $table->timestamps();

            $table->index(['courier_id', 'created_at']);
        });

        // 7. COD Remittances
        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('remittance_number')->unique();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('expected_amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'discrepancy_flagged', 'rejected'])->default('pending');
            $table->text('rider_notes')->nullable();
            $table->text('operator_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
            $table->index(['sorting_center_id', 'status']);
        });

        // 8. Read-Only Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('operator_id');
        });

        // 9. Idempotency Keys
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->integer('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['key', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cod_remittances');
        Schema::dropIfExists('cod_ledger_entries');
        Schema::dropIfExists('rider_earnings');
        Schema::dropIfExists('proof_of_deliveries');
        Schema::dropIfExists('job_offers');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_otp', 'delivery_otp_failed_attempts', 'delivery_otp_locked_until']);
        });

        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'is_online',
                'earnings_balance',
                'unremitted_cod_balance',
                'vehicle_verification_status',
                'pending_vehicle_type',
                'pending_plate_number',
                'pending_or_cr_path',
            ]);
        });
    }
};
