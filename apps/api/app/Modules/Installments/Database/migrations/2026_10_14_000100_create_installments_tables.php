<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer's debt (a credit sale, or their account) split into dated installments. The money
        // itself lives on the customer's account (Customers module): the plan's principal is already
        // there, its markup is posted when the plan is made, and payments go through «تحصيل».
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->uuid('customer_id')->index();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20)->nullable();
            $table->uuid('sale_id')->nullable()->index();
            $table->string('sale_reference', 30)->nullable();
            $table->unsignedBigInteger('principal')->comment('piasters already owed (the sale\'s credit / the account)');
            $table->unsignedBigInteger('markup')->default(0)->comment('piasters added on top (فوايد)');
            $table->unsignedInteger('markup_rate')->nullable()->comment('basis points a month it was worked out from, for display');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid')->default(0);
            $table->unsignedSmallInteger('count');
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->date('first_due_on');
            $table->string('guarantor_name', 120)->nullable();
            $table->string('guarantor_phone', 20)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 12)->default('active')->comment('active | completed | cancelled');
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->date('due_on');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid')->default(0);
            $table->timestamp('paid_at')->nullable()->comment('when it was paid in full');
            $table->timestamps();

            $table->unique(['plan_id', 'seq']);
            $table->index(['tenant_id', 'due_on']);
        });

        // Every amount applied to a plan: append-only.
        Schema::create('installment_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('installment_plans')->restrictOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('method', 12)->nullable();
            $table->string('source', 10)->comment('counter: collected on the plan | account: from «تحصيل» on the customer page');
            $table->uuid('customer_transaction_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['plan_id', 'seq']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_items');
        Schema::dropIfExists('installment_plans');
    }
};
