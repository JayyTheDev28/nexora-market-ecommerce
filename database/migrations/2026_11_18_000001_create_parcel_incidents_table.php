<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('manifest_id')->nullable()->constrained('transfer_manifests')->nullOnDelete();
            $table->foreignId('manifest_item_id')->nullable()->constrained('transfer_manifest_items')->nullOnDelete();
            $table->foreignId('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->foreignId('logistics_company_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number', 100)->index();
            $table->string('incident_type', 30)->index(); // damage, missing, discrepancy
            $table->string('damage_type', 60)->nullable();
            $table->string('source_stage', 40)->default('manifest_receiving'); // manifest_receiving, post_receipt
            $table->string('investigation_status', 30)->default('open')->index(); // open, under_investigation, found, resolved
            $table->text('description');
            $table->string('evidence_path', 255)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('found_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'incident_type', 'investigation_status'], 'parcel_incidents_order_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_incidents');
    }
};
