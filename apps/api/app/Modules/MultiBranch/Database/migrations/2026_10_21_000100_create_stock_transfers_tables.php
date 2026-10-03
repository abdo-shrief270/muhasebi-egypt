<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Goods moving between two branches of a shop (TR-00001): requested → shipped → received.
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignUuid('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('to_branch_id')->constrained('branches')->restrictOnDelete();
            // requested | shipped | received | cancelled
            $table->string('status', 16);
            $table->string('notes', 500)->nullable();
            $table->string('requested_by_name')->nullable();
            $table->string('shipped_by_name')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('received_by_name')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->uuid('variant_id');
            $table->string('name', 255);
            $table->boolean('track_serial')->default(false);
            $table->unsignedInteger('qty_requested');
            $table->unsignedInteger('qty_shipped')->default(0);
            $table->unsignedInteger('qty_received')->default(0);
            // What the shipped units cost the sending branch (FIFO), received at the same cost.
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->jsonb('serials')->nullable();
            $table->jsonb('received_serials')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
