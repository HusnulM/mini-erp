<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_runs', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 26);
            $table->string('type', 20);                     // create | install_module | upgrade | delete
            $table->string('status', 20)->default('pending'); // pending | running | done | failed
            $table->json('context')->nullable();            // e.g. module code for install_module
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'type', 'status']);
        });

        Schema::create('provisioning_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('provisioning_runs')->cascadeOnDelete();
            $table->string('step', 40);
            $table->unsignedSmallInteger('seq');
            $table->string('status', 20)->default('pending'); // pending | running | done | failed | skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_steps');
        Schema::dropIfExists('provisioning_runs');
    }
};
