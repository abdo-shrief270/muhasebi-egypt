<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->bigInteger('balance')->default(0)->comment('piasters; > 0 = we owe the supplier');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // The supplier account: append-only, like the stock ledger.
        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount')->comment('piasters; + we owe more, - we owe less');
            $table->bigInteger('balance_after');
            $table->string('payment_method', 20)->nullable();
            $table->string('ref_type', 30)->nullable();
            $table->uuid('ref_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            // Lines written in the same instant (a purchase and its payment) keep their order.
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['supplier_id', 'seq']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->date('invoice_date');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid')->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->unsignedBigInteger('returned')->default(0);
            $table->string('notes', 1000)->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['supplier_id', 'invoice_date']);
            $table->index(['tenant_id', 'invoice_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUuid('lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_cost')->comment('as invoiced');
            $table->unsignedBigInteger('net_unit_cost')->comment('after the invoice discount; what the stock costs');
            $table->unsignedBigInteger('line_total');
            $table->unsignedBigInteger('previous_cost')->nullable()->comment('average cost before this purchase');
            $table->unsignedInteger('returned_qty')->default(0);
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('purchase_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->unsignedBigInteger('total');
            $table->string('notes', 1000)->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('line_total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('supplier_transactions');
        Schema::dropIfExists('suppliers');
    }
};
