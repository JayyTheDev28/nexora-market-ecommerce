<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend parcel_incidents with full lifecycle, quarantine, custody, routing & financial hold fields
        Schema::table('parcel_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('parcel_incidents', 'incident_number')) {
                $table->string('incident_number', 40)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('parcel_incidents', 'disposition')) {
                $table->string('disposition', 60)->nullable()->index()->after('investigation_status');
            }
            if (! Schema::hasColumn('parcel_incidents', 'handling_restriction')) {
                $table->string('handling_restriction', 40)->nullable()->index()->after('disposition');
            }
            if (! Schema::hasColumn('parcel_incidents', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('resolved_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('parcel_incidents', 'last_known_location')) {
                $table->string('last_known_location', 255)->nullable()->after('evidence_path');
            }
            if (! Schema::hasColumn('parcel_incidents', 'last_known_event')) {
                $table->string('last_known_event', 255)->nullable()->after('last_known_location');
            }
            if (! Schema::hasColumn('parcel_incidents', 'expected_hub_id')) {
                $table->foreignId('expected_hub_id')->nullable()->after('hub_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('parcel_incidents', 'actual_hub_id')) {
                $table->foreignId('actual_hub_id')->nullable()->after('expected_hub_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('parcel_incidents', 'rerouted_to_hub_id')) {
                $table->foreignId('rerouted_to_hub_id')->nullable()->after('actual_hub_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('parcel_incidents', 'quarantined_at')) {
                $table->timestamp('quarantined_at')->nullable()->after('reported_at');
            }
            if (! Schema::hasColumn('parcel_incidents', 'quarantined_by')) {
                $table->foreignId('quarantined_by')->nullable()->after('quarantined_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('parcel_incidents', 'recovered_at')) {
                $table->timestamp('recovered_at')->nullable()->after('found_at');
            }
            if (! Schema::hasColumn('parcel_incidents', 'recovered_by')) {
                $table->foreignId('recovered_by')->nullable()->after('recovered_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('parcel_incidents', 'recovery_location')) {
                $table->string('recovery_location', 255)->nullable()->after('recovered_by');
            }
            if (! Schema::hasColumn('parcel_incidents', 'recovery_evidence_path')) {
                $table->string('recovery_evidence_path', 255)->nullable()->after('recovery_location');
            }
            if (! Schema::hasColumn('parcel_incidents', 'financial_hold_status')) {
                $table->string('financial_hold_status', 40)->nullable()->index()->after('recovery_evidence_path');
            }
            if (! Schema::hasColumn('parcel_incidents', 'due_at')) {
                $table->timestamp('due_at')->nullable()->index()->after('financial_hold_status');
            }
            if (! Schema::hasColumn('parcel_incidents', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('parcel_incidents', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill incident_number for any existing records
        $existing = DB::table('parcel_incidents')->whereNull('incident_number')->orderBy('id')->get();
        foreach ($existing as $row) {
            $datePrefix = $row->reported_at ? date('Ymd', strtotime($row->reported_at)) : date('Ymd');
            DB::table('parcel_incidents')->where('id', $row->id)->update([
                'incident_number' => sprintf('INC-%s-%04d', $datePrefix, $row->id),
            ]);
        }

        // 2. Append-only incident investigation & custody timeline table
        if (! Schema::hasTable('parcel_incident_events')) {
            Schema::create('parcel_incident_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incident_id')->constrained('parcel_incidents')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 40)->nullable();
                $table->string('event_type', 60)->index();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->string('disposition', 60)->nullable();
                $table->string('location', 255)->nullable();
                $table->text('notes')->nullable();
                $table->string('evidence_path', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamps();
            });
        }

        // 3. Extend transfer_manifests for controlled Closed With Exceptions reconciliation
        Schema::table('transfer_manifests', function (Blueprint $table) {
            if (! Schema::hasColumn('transfer_manifests', 'closed_with_exceptions_at')) {
                $table->timestamp('closed_with_exceptions_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('transfer_manifests', 'closed_with_exceptions_by')) {
                $table->foreignId('closed_with_exceptions_by')->nullable()->after('closed_with_exceptions_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('transfer_manifests', 'reconciliation_notes')) {
                $table->text('reconciliation_notes')->nullable()->after('notes');
            }
        });

        // 4. Extend transfer_manifest_items with courier issue evidence/location & item disposition
        Schema::table('transfer_manifest_items', function (Blueprint $table) {
            if (! Schema::hasColumn('transfer_manifest_items', 'courier_issue_type')) {
                $table->string('courier_issue_type', 30)->nullable()->after('issue_notes');
            }
            if (! Schema::hasColumn('transfer_manifest_items', 'courier_issue_reported_at')) {
                $table->timestamp('courier_issue_reported_at')->nullable()->after('courier_issue_type');
            }
            if (! Schema::hasColumn('transfer_manifest_items', 'courier_issue_evidence_path')) {
                $table->string('courier_issue_evidence_path', 255)->nullable()->after('courier_issue_reported_at');
            }
            if (! Schema::hasColumn('transfer_manifest_items', 'courier_issue_location')) {
                $table->string('courier_issue_location', 255)->nullable()->after('courier_issue_evidence_path');
            }
            if (! Schema::hasColumn('transfer_manifest_items', 'disposition')) {
                $table->string('disposition', 60)->nullable()->after('courier_issue_location');
            }
        });

        // 5. Extend orders with handling_status & reroute traceability (never overwriting historical hubs silently)
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'handling_status')) {
                $table->string('handling_status', 40)->nullable()->index()->after('status');
            }
            if (! Schema::hasColumn('orders', 'rerouted_from_hub_id')) {
                $table->foreignId('rerouted_from_hub_id')->nullable()->after('current_hub_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'reroute_count')) {
                $table->unsignedSmallInteger('reroute_count')->default(0)->after('rerouted_from_hub_id');
            }
        });

        // 6. Extend disputes with separated Return / Inspection / Restock / Refund verification stages
        Schema::table('disputes', function (Blueprint $table) {
            if (! Schema::hasColumn('disputes', 'return_stage')) {
                $table->string('return_stage', 40)->default('requested')->index()->after('seller_status');
            }
            if (! Schema::hasColumn('disputes', 'return_tracking_number')) {
                $table->string('return_tracking_number', 100)->nullable()->after('return_instructions');
            }
            if (! Schema::hasColumn('disputes', 'return_received_at')) {
                $table->timestamp('return_received_at')->nullable()->after('return_tracking_number');
            }
            if (! Schema::hasColumn('disputes', 'return_received_by')) {
                $table->foreignId('return_received_by')->nullable()->after('return_received_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('disputes', 'inspection_condition')) {
                $table->string('inspection_condition', 40)->nullable()->after('return_received_by');
            }
            if (! Schema::hasColumn('disputes', 'inspection_notes')) {
                $table->text('inspection_notes')->nullable()->after('inspection_condition');
            }
            if (! Schema::hasColumn('disputes', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable()->after('inspection_notes');
            }
            if (! Schema::hasColumn('disputes', 'inspected_by')) {
                $table->foreignId('inspected_by')->nullable()->after('inspected_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('disputes', 'restock_approved')) {
                $table->boolean('restock_approved')->default(false)->after('inspected_by');
            }
            if (! Schema::hasColumn('disputes', 'restocked_at')) {
                $table->timestamp('restocked_at')->nullable()->after('restock_approved');
            }
            if (! Schema::hasColumn('disputes', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->nullable()->after('restocked_at');
            }
            if (! Schema::hasColumn('disputes', 'refund_authorized_at')) {
                $table->timestamp('refund_authorized_at')->nullable()->after('refund_amount');
            }
            if (! Schema::hasColumn('disputes', 'refund_authorized_by')) {
                $table->foreignId('refund_authorized_by')->nullable()->after('refund_authorized_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('disputes', 'refund_method')) {
                $table->string('refund_method', 50)->nullable()->after('refund_authorized_by');
            }
            if (! Schema::hasColumn('disputes', 'refund_reference')) {
                $table->string('refund_reference', 120)->nullable()->after('refund_method');
            }
            if (! Schema::hasColumn('disputes', 'refund_recorded_at')) {
                $table->timestamp('refund_recorded_at')->nullable()->after('refund_reference');
            }
            if (! Schema::hasColumn('disputes', 'refund_recorded_by')) {
                $table->foreignId('refund_recorded_by')->nullable()->after('refund_recorded_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('disputes', 'refund_notes')) {
                $table->text('refund_notes')->nullable()->after('refund_recorded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            foreach (['return_received_by', 'inspected_by', 'refund_authorized_by', 'refund_recorded_by'] as $fk) {
                if (Schema::hasColumn('disputes', $fk)) {
                    $table->dropForeign([$fk]);
                }
            }
            $cols = array_filter([
                'return_stage', 'return_tracking_number', 'return_received_at', 'return_received_by',
                'inspection_condition', 'inspection_notes', 'inspected_at', 'inspected_by',
                'restock_approved', 'restocked_at', 'refund_amount', 'refund_authorized_at',
                'refund_authorized_by', 'refund_method', 'refund_reference', 'refund_recorded_at',
                'refund_recorded_by', 'refund_notes',
            ], fn ($c) => Schema::hasColumn('disputes', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'rerouted_from_hub_id')) {
                $table->dropForeign(['rerouted_from_hub_id']);
            }
            $cols = array_filter(['handling_status', 'rerouted_from_hub_id', 'reroute_count'], fn ($c) => Schema::hasColumn('orders', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('transfer_manifest_items', function (Blueprint $table) {
            $cols = array_filter([
                'courier_issue_type', 'courier_issue_reported_at',
                'courier_issue_evidence_path', 'courier_issue_location', 'disposition',
            ], fn ($c) => Schema::hasColumn('transfer_manifest_items', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('transfer_manifests', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_manifests', 'closed_with_exceptions_by')) {
                $table->dropForeign(['closed_with_exceptions_by']);
            }
            $cols = array_filter(['closed_with_exceptions_at', 'closed_with_exceptions_by', 'reconciliation_notes'], fn ($c) => Schema::hasColumn('transfer_manifests', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('parcel_incident_events');

        Schema::table('parcel_incidents', function (Blueprint $table) {
            foreach (['assigned_to', 'expected_hub_id', 'actual_hub_id', 'rerouted_to_hub_id', 'quarantined_by', 'recovered_by', 'closed_by'] as $fk) {
                if (Schema::hasColumn('parcel_incidents', $fk)) {
                    $table->dropForeign([$fk]);
                }
            }
            $cols = array_filter([
                'incident_number', 'disposition', 'handling_restriction', 'assigned_to',
                'last_known_location', 'last_known_event', 'expected_hub_id', 'actual_hub_id',
                'rerouted_to_hub_id', 'quarantined_at', 'quarantined_by', 'recovered_at',
                'recovered_by', 'recovery_location', 'recovery_evidence_path',
                'financial_hold_status', 'due_at', 'closed_at', 'closed_by',
            ], fn ($c) => Schema::hasColumn('parcel_incidents', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
