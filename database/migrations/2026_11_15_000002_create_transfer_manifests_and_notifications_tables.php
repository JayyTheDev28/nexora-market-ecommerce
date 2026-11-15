<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Transfer Manifests
        Schema::create('transfer_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_number', 50)->unique();
            $table->foreignId('sender_logistics_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('origin_hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignId('receiver_logistics_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destination_hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignId('assigned_courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vehicle_plate_number', 30)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('total_parcels')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Link hub_transfers to transfer_manifests (nullable for backward compatibility with single-parcel transfers)
        Schema::table('hub_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('hub_transfers', 'manifest_id')) {
                $table->foreignId('manifest_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('transfer_manifests')
                    ->nullOnDelete();
            }
        });

        // 3. Transfer Manifest Items
        Schema::create('transfer_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained('transfer_manifests')->cascadeOnDelete();
            $table->foreignId('hub_transfer_id')->nullable()->constrained('hub_transfers')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tracking_number', 100)->index();
            $table->string('loading_status', 30)->default('pending'); // pending, loaded, issue
            $table->timestamp('loaded_at')->nullable();
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receiving_status', 30)->default('pending'); // pending, received, missing, damaged, discrepancy
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('issue_notes')->nullable();
            $table->timestamps();

            $table->unique(['manifest_id', 'order_id']);
        });

        // 4. Standard Laravel notifications table if not present
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_manifest_items');

        Schema::table('hub_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('hub_transfers', 'manifest_id')) {
                $table->dropForeign(['manifest_id']);
                $table->dropColumn('manifest_id');
            }
        });

        Schema::dropIfExists('transfer_manifests');
    }
};
