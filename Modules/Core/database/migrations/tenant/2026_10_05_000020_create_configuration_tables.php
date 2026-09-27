<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration & platform tables (TDD §5 "Konfigurasi & platform").
 */
return new class extends Migration
{
    public function up(): void
    {
        // company_id NULL = tenant level. Resolve order: company → tenant → default.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('key', 100);
            $table->json('value')->nullable();
            // Generated column so the unique key also holds for tenant-level rows
            // (MySQL treats NULLs as distinct in unique indexes).
            $table->unsignedBigInteger('company_key')->virtualAs('IFNULL(company_id, 0)');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['company_key', 'module', 'key']);
        });

        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->string('group', 60);
            $table->string('code', 40);
            $table->string('label', 150);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);       // cannot be deleted
            $table->timestamps();
            $table->unique(['module', 'group', 'code']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_key')->virtualAs('IFNULL(branch_id, 0)');
            $table->string('module', 40);
            $table->string('document_type', 20);
            $table->string('prefix', 20);
            $table->string('format', 60)->default('{PREFIX}-{YYYY}-{SEQ:6}');
            $table->string('reset_period', 10)->default('yearly'); // never | yearly | monthly
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('current_period', 7)->nullable();      // 2026 or 2026-09
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_key', 'document_type']);
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency_code', 3);
            $table->date('date');
            $table->decimal('rate', 19, 6);                       // 1 unit of currency in base currency
            $table->timestamps();
            $table->unique(['currency_code', 'date']);
        });

        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->decimal('rate', 7, 4);
            $table->string('type', 10);                           // input | output
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('account_id')->nullable(); // filled when Finance is active
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code', 'effective_from']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open');       // open | closed | locked
            $table->timestamps();
            $table->unique(['company_id', 'year']);
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('period');                // 1..12
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open');
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'period']);
        });

        // Append-only (PRD §49): rows are never updated or deleted from the app.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event', 30);                          // created | updated | deleted | restored | ...
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('setup_progress', function (Blueprint $table) {
            $table->id();
            $table->string('step', 60)->unique();
            $table->string('status', 20)->default('pending');   // pending | done | skipped
            $table->timestamp('completed_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Outbox for cross-module domain events (TDD §6); processing comes with the modules.
        Schema::create('module_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'event_type']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'module_events', 'setup_progress', 'audit_logs', 'fiscal_periods', 'fiscal_years',
            'tax_codes', 'exchange_rates', 'currencies', 'document_sequences', 'lookups', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
