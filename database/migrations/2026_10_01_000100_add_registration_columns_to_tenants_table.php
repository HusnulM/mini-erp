<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 registration: the owner's email must be verified before a
 * database is provisioned, and one email can own only one tenant (TDD §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('phone');
            $table->dropIndex(['owner_email']);
            $table->unique('owner_email');
            // Purge of unverified registrations (status pending, older than 7 days).
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropUnique(['owner_email']);
            $table->index('owner_email');
            $table->dropColumn('email_verified_at');
        });
    }
};
