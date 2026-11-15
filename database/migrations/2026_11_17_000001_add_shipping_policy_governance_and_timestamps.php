<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_rate_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_rate_policies', 'review_status')) {
                $table->string('review_status', 30)->default('pending_admin_review')->after('status');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'contract_reference')) {
                $table->string('contract_reference', 120)->nullable()->after('review_status');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'effective_until')) {
                $table->timestamp('effective_until')->nullable()->after('effective_from');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('inter_company_shipping_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('inter_company_shipping_allocations', 'allocation_mode')) {
                $table->string('allocation_mode', 40)->default('single_carrier')->after('collecting_logistics_id');
            }
            if (! Schema::hasColumn('inter_company_shipping_allocations', 'contract_reference')) {
                $table->string('contract_reference', 120)->nullable()->after('allocation_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inter_company_shipping_allocations', function (Blueprint $table) {
            $table->dropColumn(['allocation_mode', 'contract_reference']);
        });

        Schema::table('shipping_rate_policies', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'review_status',
                'contract_reference',
                'effective_until',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
            ]);
        });
    }
};
