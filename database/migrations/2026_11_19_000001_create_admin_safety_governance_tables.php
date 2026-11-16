<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('safety_reports')) {
            Schema::create('safety_reports', function (Blueprint $table) {
                $table->id();
                $table->string('reference_number', 40)->unique();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->string('reporter_role', 30);
                $table->string('category', 60);
                $table->string('subject_type', 40)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('parcel_incident_id')->nullable()->constrained('parcel_incidents')->nullOnDelete();
                $table->foreignId('dispute_id')->nullable()->constrained('disputes')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description');
                $table->json('evidence_paths')->nullable();
                $table->string('status', 30)->default('new');
                $table->string('priority', 20)->default('medium');
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_summary')->nullable();
                $table->text('closed_reason')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'priority']);
                $table->index(['reporter_id', 'created_at']);
                $table->index(['reported_user_id', 'status']);
            });
        }

        if (! Schema::hasTable('safety_report_notes')) {
            Schema::create('safety_report_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('safety_report_id')->constrained('safety_reports')->cascadeOnDelete();
                $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_internal')->default(true);
                $table->text('note');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_restrictions')) {
            Schema::create('account_restrictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('restriction_type', 40);
                $table->text('reason');
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('safety_report_id')->nullable()->constrained('safety_reports')->nullOnDelete();
                $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('starts_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('lifted_at')->nullable();
                $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('lift_reason')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['user_id', 'restriction_type', 'status']);
            });
        }

        if (! Schema::hasTable('restriction_appeals')) {
            Schema::create('restriction_appeals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_restriction_id')->constrained('account_restrictions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('reason');
                $table->json('evidence_paths')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risk_alerts')) {
            Schema::create('risk_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('rule_code', 60);
                $table->string('title', 200);
                $table->string('severity', 20)->default('medium');
                $table->string('entity_type', 40)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('supporting_data')->nullable();
                $table->string('status', 25)->default('open');
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('decision_notes')->nullable();
                $table->timestamp('detected_at')->useCurrent();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['rule_code', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'admin_permissions')) {
                $table->json('admin_permissions')->nullable()->after('account_status');
            }
            if (! Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('admin_permissions');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
            }
            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });

        Schema::table('disputes', function (Blueprint $table) {
            if (! Schema::hasColumn('disputes', 'return_status')) {
                $table->string('return_status', 40)->default('none')->after('resolution_note');
            }
            if (! Schema::hasColumn('disputes', 'return_tracking_number')) {
                $table->string('return_tracking_number', 100)->nullable()->after('return_status');
            }
            if (! Schema::hasColumn('disputes', 'return_inspection_notes')) {
                $table->text('return_inspection_notes')->nullable()->after('return_tracking_number');
            }
            if (! Schema::hasColumn('disputes', 'inventory_disposition')) {
                $table->string('inventory_disposition', 40)->nullable()->after('return_inspection_notes');
            }
            if (! Schema::hasColumn('disputes', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 2)->nullable()->after('inventory_disposition');
            }
            if (! Schema::hasColumn('disputes', 'refund_reference')) {
                $table->string('refund_reference', 100)->nullable()->after('refund_amount');
            }
            if (! Schema::hasColumn('disputes', 'return_authorized_at')) {
                $table->timestamp('return_authorized_at')->nullable()->after('refund_reference');
            }
            if (! Schema::hasColumn('disputes', 'return_received_at')) {
                $table->timestamp('return_received_at')->nullable()->after('return_authorized_at');
            }
            if (! Schema::hasColumn('disputes', 'return_inspected_at')) {
                $table->timestamp('return_inspected_at')->nullable()->after('return_received_at');
            }
            if (! Schema::hasColumn('disputes', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('return_inspected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $cols = array_filter([
                'return_status',
                'return_inspection_notes',
                'inventory_disposition',
                'refund_amount',
                'refund_reference',
                'return_authorized_at',
                'return_received_at',
                'return_inspected_at',
                'refunded_at',
            ], fn ($c) => Schema::hasColumn('disputes', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter([
                'admin_permissions',
                'two_factor_enabled',
                'last_login_at',
                'last_login_ip',
            ], fn ($c) => Schema::hasColumn('users', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('risk_alerts');
        Schema::dropIfExists('restriction_appeals');
        Schema::dropIfExists('account_restrictions');
        Schema::dropIfExists('safety_report_notes');
        Schema::dropIfExists('safety_reports');
    }
};
