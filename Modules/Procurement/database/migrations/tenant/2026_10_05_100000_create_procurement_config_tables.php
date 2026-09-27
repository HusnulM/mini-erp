<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement configuration (TDD §9 "Contoh: konfigurasi Procurement").
 * Transactions (PR, PO, GR) come in Phase 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('requires_pr')->default(true);
            $table->boolean('requires_gr')->default(true);       // Service = false
            $table->foreignId('pr_sequence_id')->nullable()->constrained('document_sequences')->nullOnDelete();
            $table->foreignId('po_sequence_id')->nullable()->constrained('document_sequences')->nullOnDelete();
            $table->unsignedBigInteger('pr_workflow_id')->nullable(); // approval_workflows (Workflow module)
            $table->unsignedBigInteger('po_workflow_id')->nullable();
            $table->json('allowed_product_types')->nullable();     // ["stock", "non_stock"]
            $table->string('account_mapping_key', 40)->nullable(); // used by Finance
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('purchasing_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('max_po_amount', 19, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('purchasing_group_members', function (Blueprint $table) {
            $table->foreignId('purchasing_group_id')->constrained('purchasing_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10)->default('buyer');          // buyer | lead
            $table->primary(['purchasing_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_group_members');
        Schema::dropIfExists('purchasing_groups');
        Schema::dropIfExists('procurement_types');
    }
};
