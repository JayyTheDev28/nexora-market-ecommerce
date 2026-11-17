<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_rate_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_rate_policies', 'pricing_mode')) {
                $table->string('pricing_mode', 30)->default('weight_tiered')->after('status');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'target_profit_margin_pct')) {
                $table->decimal('target_profit_margin_pct', 5, 2)->default(20.00)->after('minimum_fee');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'rounding_increment')) {
                $table->decimal('rounding_increment', 8, 2)->default(0.00)->after('target_profit_margin_pct');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'estimated_pickup_cost')) {
                $table->decimal('estimated_pickup_cost', 10, 2)->default(10.00)->after('rounding_increment');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'estimated_delivery_cost')) {
                $table->decimal('estimated_delivery_cost', 10, 2)->default(30.00)->after('estimated_pickup_cost');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'estimated_transfer_cost_per_parcel')) {
                $table->decimal('estimated_transfer_cost_per_parcel', 10, 2)->default(20.00)->after('estimated_delivery_cost');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'estimated_hub_operating_cost')) {
                $table->decimal('estimated_hub_operating_cost', 10, 2)->default(10.00)->after('estimated_transfer_cost_per_parcel');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'same_province_surcharge')) {
                $table->decimal('same_province_surcharge', 10, 2)->default(0.00)->after('estimated_hub_operating_cost');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'interprovincial_surcharge')) {
                $table->decimal('interprovincial_surcharge', 10, 2)->default(0.00)->after('same_province_surcharge');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'oversized_surcharge')) {
                $table->decimal('oversized_surcharge', 10, 2)->default(0.00)->after('interprovincial_surcharge');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'volumetric_divisor')) {
                $table->unsignedInteger('volumetric_divisor')->default(5000)->after('oversized_surcharge');
            }
            if (! Schema::hasColumn('shipping_rate_policies', 'route_rules')) {
                $table->json('route_rules')->nullable()->after('volumetric_divisor');
            }
        });

        Schema::table('compensation_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('compensation_policies', 'distance_allowance_rate')) {
                $table->decimal('distance_allowance_rate', 10, 2)->default(0.00)->after('failed_attempt_rate');
            }
            if (! Schema::hasColumn('compensation_policies', 'batch_completion_bonus')) {
                $table->decimal('batch_completion_bonus', 10, 2)->default(0.00)->after('distance_allowance_rate');
            }
            if (! Schema::hasColumn('compensation_policies', 'expected_manifest_parcels')) {
                $table->unsignedInteger('expected_manifest_parcels')->default(20)->after('pay_on_exception');
            }
        });

        Schema::table('transfer_manifests', function (Blueprint $table) {
            if (! Schema::hasColumn('transfer_manifests', 'expected_parcel_count')) {
                $table->unsignedInteger('expected_parcel_count')->nullable()->after('total_parcels');
            }
            if (! Schema::hasColumn('transfer_manifests', 'estimated_trip_cost')) {
                $table->decimal('estimated_trip_cost', 12, 2)->nullable()->after('expected_parcel_count');
            }
            if (! Schema::hasColumn('transfer_manifests', 'expected_cost_per_parcel')) {
                $table->decimal('expected_cost_per_parcel', 10, 2)->nullable()->after('estimated_trip_cost');
            }
            if (! Schema::hasColumn('transfer_manifests', 'actual_cost_per_parcel')) {
                $table->decimal('actual_cost_per_parcel', 10, 2)->nullable()->after('expected_cost_per_parcel');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shipping_route_type')) {
                $table->string('shipping_route_type', 30)->nullable()->after('shipping_rate_policy_id');
            }
            if (! Schema::hasColumn('orders', 'shipping_voucher_funder')) {
                $table->string('shipping_voucher_funder', 30)->nullable()->after('shipping_voucher_id');
            }
            if (! Schema::hasColumn('orders', 'shipping_quote_breakdown')) {
                $table->json('shipping_quote_breakdown')->nullable()->after('shipping_route_type');
            }
        });

        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'funding_source')) {
                $table->string('funding_source', 30)->default('platform')->after('voucher_scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'funding_source')) {
                $table->dropColumn('funding_source');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['shipping_quote_breakdown', 'shipping_voucher_funder', 'shipping_route_type'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('transfer_manifests', function (Blueprint $table) {
            foreach (['actual_cost_per_parcel', 'expected_cost_per_parcel', 'estimated_trip_cost', 'expected_parcel_count'] as $col) {
                if (Schema::hasColumn('transfer_manifests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('compensation_policies', function (Blueprint $table) {
            foreach (['expected_manifest_parcels', 'batch_completion_bonus', 'distance_allowance_rate'] as $col) {
                if (Schema::hasColumn('compensation_policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('shipping_rate_policies', function (Blueprint $table) {
            foreach ([
                'route_rules', 'volumetric_divisor', 'oversized_surcharge', 'interprovincial_surcharge',
                'same_province_surcharge', 'estimated_hub_operating_cost', 'estimated_transfer_cost_per_parcel',
                'estimated_delivery_cost', 'estimated_pickup_cost', 'rounding_increment',
                'target_profit_margin_pct', 'pricing_mode',
            ] as $col) {
                if (Schema::hasColumn('shipping_rate_policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
