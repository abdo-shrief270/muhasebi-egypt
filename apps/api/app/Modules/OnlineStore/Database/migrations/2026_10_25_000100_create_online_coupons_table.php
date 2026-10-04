<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Discount codes for the online store's orders.
        Schema::create('online_coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Upper case, as typed by customers (matched case-insensitively).
            $table->string('code', 32);
            // percent (value 1–90) | amount (value in piasters)
            $table->string('kind', 8);
            $table->unsignedBigInteger('value');
            // The goods (before the discount) must reach this; 0 = any order.
            $table->unsignedBigInteger('min_order')->default(0);
            // A percent coupon's ceiling, in piasters.
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Orders that may use it (cancelled orders give their use back); null = no limit.
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            // One (not cancelled) order per phone number.
            $table->boolean('once_per_phone')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code', 32)->nullable();
            // Off the goods; total = subtotal − discount + delivery fee.
            $table->unsignedBigInteger('discount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', fn (Blueprint $table) => $table->dropColumn(['coupon_id', 'coupon_code', 'discount']));
        Schema::dropIfExists('online_coupons');
    }
};
