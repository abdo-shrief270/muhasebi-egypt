<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two shops that agreed to order from each other.
        Schema::create('shop_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requester_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('addressee_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 16);
            $table->uuid('requested_by');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['requester_tenant_id', 'addressee_tenant_id']);
            $table->index(['addressee_tenant_id', 'status']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS shop_order_number_seq');

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('number')->unique();
            $table->foreignUuid('buyer_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('seller_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('status', 16);
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('total')->nullable()->comment('piasters, set by the seller');
            $table->uuid('placed_by');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_tenant_id', 'status']);
            $table->index(['seller_tenant_id', 'status']);
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->uuid('buyer_tenant_id');
            $table->uuid('seller_tenant_id');
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->nullable()->comment('piasters');
            $table->string('device_model')->nullable();
            $table->string('imei', 20)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->uuid('buyer_tenant_id');
            $table->uuid('seller_tenant_id');
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('actor_party', 8);
            $table->uuid('actor_user_id');
            $table->string('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_activities');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        DB::statement('DROP SEQUENCE IF EXISTS shop_order_number_seq');
        Schema::dropIfExists('shop_connections');
    }
};
