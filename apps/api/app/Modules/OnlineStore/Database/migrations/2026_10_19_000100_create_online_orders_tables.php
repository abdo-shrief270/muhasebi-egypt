<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ordering on the store (mode `orders`): how customers receive and pay.
        Schema::table('online_stores', function (Blueprint $table) {
            $table->boolean('pickup')->default(true);
            $table->boolean('delivery')->default(false);
            $table->unsignedBigInteger('min_order')->default(0);
            $table->unsignedBigInteger('free_delivery_over')->nullable();
            $table->boolean('pay_cod')->default(true);
            $table->boolean('pay_transfer')->default(false);
            $table->string('transfer_instapay', 60)->nullable();
            $table->string('transfer_wallet', 20)->nullable();
        });

        Schema::create('online_delivery_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedBigInteger('fee');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'sort']);
        });

        Schema::create('online_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // The store's branch when it was placed: whose stock it shows and where it's prepared.
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 24);
            $table->uuid('customer_id')->nullable()->index();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            // pickup | delivery
            $table->string('fulfilment', 16);
            $table->uuid('zone_id')->nullable();
            $table->string('zone_name', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('notes', 500)->nullable();
            // cod (cash on delivery / pickup) | transfer (InstaPay / wallet with a proof photo)
            $table->string('payment', 16);
            $table->string('proof', 64)->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('total');
            // Public tracking page /o/{token}.
            $table->string('token', 40)->unique();
            $table->boolean('consent')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            // The invoice it became (Sales), and whether the delivery fee went into the drawer with it.
            $table->uuid('sale_id')->nullable();
            $table->string('sale_reference', 40)->nullable();
            $table->boolean('fee_collected')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'customer_phone']);
        });

        Schema::create('online_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('online_orders')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->string('name', 255);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
        });

        // The order's timeline. Append-only.
        Schema::create('online_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('online_orders')->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('note', 500)->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['order_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_events');
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
        Schema::dropIfExists('online_delivery_zones');
        Schema::table('online_stores', function (Blueprint $table) {
            $table->dropColumn(['pickup', 'delivery', 'min_order', 'free_delivery_over', 'pay_cod', 'pay_transfer', 'transfer_instapay', 'transfer_wallet']);
        });
    }
};
