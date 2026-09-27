<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization master data (TDD §5 "Organisasi"). Every master table has the
 * PRD §55 standard columns: code, name, status, created_by, updated_by,
 * timestamps, deleted_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('tax_id', 30)->nullable();              // NPWP
            $table->text('address')->nullable();
            $table->char('base_currency', 3)->default('IDR');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('type', 20)->default('main');         // main | store | transit
            $table->boolean('allow_negative_stock')->default(false);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses');
            $table->unsignedBigInteger('price_list_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations');
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['warehouse_id', 'code']);
        });

        // Data scope of a user (PRD §8): which organization units they may see.
        Schema::create('user_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type', 20);                    // company | branch | store | warehouse | own
            $table->unsignedBigInteger('scope_id')->nullable();  // null for "own"
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_scopes');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
