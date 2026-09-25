<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 19, 4)->default(0);
            $table->decimal('price_yearly', 19, 4)->default(0);
            $table->unsignedInteger('max_users')->nullable();      // null = unlimited
            $table->unsignedInteger('max_companies')->nullable();
            $table->unsignedInteger('max_stores')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Synced from Modules/*/module.json by `php artisan modules:sync`.
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_core')->default(false);
            $table->boolean('is_addon')->default(false);
            $table->decimal('price_monthly', 19, 4)->default(0);
            $table->string('version', 20);
            $table->string('status', 20)->default('available'); // available | beta | deprecated
            $table->json('manifest')->nullable();                // last synced module.json
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('module_dependencies', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('requires_module_id')->constrained('modules')->cascadeOnDelete();
            $table->boolean('is_optional')->default(false);
            $table->primary(['module_id', 'requires_module_id']);
        });

        Schema::create('plan_modules', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->primary(['plan_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_modules');
        Schema::dropIfExists('module_dependencies');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('plans');
    }
};
