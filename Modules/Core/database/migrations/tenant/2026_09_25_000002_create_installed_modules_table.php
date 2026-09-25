<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local record of which modules have been migrated into this tenant DB.
 * Mirrors central `tenant_modules` in SaaS mode; the only source in on-prem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installed_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 40)->unique();
            $table->string('version', 20);
            $table->timestamp('installed_at');
            $table->timestamp('last_migrated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
    }
};
