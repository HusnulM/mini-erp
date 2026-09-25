<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 26);
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft | open | paid | void | overdue
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        Schema::create('billing_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('type', 20);                     // plan | addon | proration | discount
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('description');
            $table->decimal('qty', 18, 4)->default(1);
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('amount', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('billing_invoices');
            $table->string('gateway', 20);                  // midtrans | xendit | manual
            $table->string('gateway_ref', 100)->unique();   // webhook idempotency
            $table->string('method', 30)->nullable();       // va | qris | card | transfer
            $table->decimal('amount', 19, 4);
            $table->string('status', 20);                   // pending | paid | failed | expired | refunded
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
    }
};
