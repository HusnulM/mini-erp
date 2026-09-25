<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 26)->primary();            // ULID, not guessable
            $table->string('code', 30)->unique();         // human reference, e.g. TOKOABC
            $table->string('name', 150);
            $table->string('slug', 30)->unique();         // subdomain
            $table->string('owner_name', 150);
            $table->string('owner_email')->index();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('mode', 10)->default('saas');  // saas | onprem

            // Tenant database. Credentials are encrypted with APP_KEY.
            $table->string('db_name', 64)->nullable()->unique();
            $table->string('db_username', 32)->nullable()->unique();
            $table->text('db_password')->nullable();
            $table->string('db_host')->nullable();        // null = default host; set to shard

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();             // stancl virtual column
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
