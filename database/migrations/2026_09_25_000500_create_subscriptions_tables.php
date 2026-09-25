<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 26);
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status', 20)->index();         // trialing | active | past_due | cancelled | expired
            $table->string('billing_cycle', 10);           // monthly | yearly
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules');
            $table->decimal('price', 19, 4)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        // Source of truth for SaaS entitlement (TDD §6 Entitlement).
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 26);
            $table->foreignId('module_id')->constrained('modules');
            $table->string('status', 20)->default('inactive'); // installing | active | readonly | inactive | failed
            $table->string('source', 10);                      // core | plan | addon | license
            $table->string('installed_version', 20)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('subscriptions');
    }
};
