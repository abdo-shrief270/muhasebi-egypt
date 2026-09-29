<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Balances, kept in step with stock_movements inside the same transaction.
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('qty')->default(0)->comment('may go negative: selling before stock is recorded is allowed');
            $table->unsignedBigInteger('avg_cost')->default(0)->comment('weighted average, piasters');
            $table->timestamps();

            $table->unique(['branch_id', 'variant_id']);
            $table->index(['tenant_id', 'branch_id']);
        });

        // Each receipt is a lot with its own cost; issues consume lots first-in first-out.
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('source_type', 20);
            $table->string('source_id', 64)->nullable();
            $table->unsignedBigInteger('unit_cost')->comment('piasters');
            $table->unsignedInteger('qty_in');
            $table->unsignedInteger('qty_remaining');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['branch_id', 'variant_id', 'received_at']);
        });

        // The ledger: append-only, never updated or deleted.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUuid('lot_id')->nullable()->constrained('stock_lots')->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('qty')->comment('+ in / - out');
            $table->unsignedBigInteger('unit_cost')->comment('piasters');
            $table->integer('balance_after');
            $table->string('ref_type', 30)->nullable();
            $table->string('ref_id', 64)->nullable();
            $table->string('reason', 20)->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at');

            $table->index(['branch_id', 'variant_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('stock_levels');
    }
};
