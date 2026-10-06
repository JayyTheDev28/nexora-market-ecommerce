<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends Laravel's default users table (id, name, email,
     * email_verified_at, password, remember_token, timestamps) with the
     * fields every Nexora role's registration form collects, plus the
     * fields that drive the auth gate: role, approval_status,
     * email_verified_at (already present by default).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');

            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('middle_initial', 10)->nullable()->after('last_name');
            $table->enum('sex', ['male', 'female', 'other'])->after('middle_initial');
            $table->date('birthday')->after('sex');
            $table->unsignedTinyInteger('age')->after('birthday');
            $table->string('contact_number')->after('age');

            $table->enum('role', ['buyer', 'seller', 'courier', 'sorting_center', 'admin'])
                ->default('buyer')
                ->after('password');

            // Gate that sits alongside email_verified_at: an account can be
            // email-verified but still waiting on human approval.
            $table->enum('approval_status', ['pending', 'approved', 'disapproved'])
                ->default('pending')
                ->after('role');
            $table->text('disapproval_reason')->nullable()->after('approval_status');

            // Every role uploads a valid ID at registration.
            $table->string('valid_id_path')->nullable()->after('disapproval_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'age',
                'contact_number', 'role', 'approval_status', 'disapproval_reason', 'valid_id_path',
            ]);
            $table->string('name')->nullable();
        });
    }
};
