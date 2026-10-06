<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * approval_status covers the one-time application decision
     * (pending/approved/disapproved). account_status is separate and
     * ongoing: the admin can suspend or deactivate an already-approved
     * account, per "Manage user accounts" in the ERP spec.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['active', 'suspended', 'deactivated'])
                ->default('active')
                ->after('disapproval_reason');
            $table->text('status_reason')->nullable()->after('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_status', 'status_reason']);
        });
    }
};
