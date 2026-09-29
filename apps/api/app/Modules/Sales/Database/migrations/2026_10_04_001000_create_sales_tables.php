<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            // The POS generates the id, so a sale sent twice (retry, offline sync) is saved once.
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('status', 20);
            $table->string('price_level', 20)->default('retail');
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->unsignedBigInteger('subtotal')->comment('after line discounts');
            $table->unsignedBigInteger('discount')->default(0)->comment('on the whole invoice');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid');
            $table->unsignedBigInteger('change')->default(0);
            $table->unsignedBigInteger('cost_total');
            $table->unsignedBigInteger('refunded')->default(0);
            $table->unsignedBigInteger('refunded_cost')->default(0);
            $table->string('notes', 500)->nullable();
            $table->uuid('cashier_id')->nullable();
            $table->string('cashier_name')->nullable();
            $table->string('public_token', 40)->unique()->comment('for the receipt link / QR code');
            $table->timestamp('completed_at', 6);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'completed_at']);
            $table->index(['branch_id', 'completed_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('name')->comment('as sold, for receipts');
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount')->default(0)->comment('on this line');
            $table->unsignedBigInteger('line_total');
            $table->unsignedBigInteger('unit_cost')->comment('FIFO cost of what left stock');
            $table->unsignedInteger('returned_qty')->default(0);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->unsignedBigInteger('amount');
            $table->string('reference', 60)->nullable();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('cost');
            $table->string('refund_method', 20);
            $table->string('reason', 500)->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_refund');
            $table->unsignedBigInteger('line_total');
            $table->boolean('restocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
