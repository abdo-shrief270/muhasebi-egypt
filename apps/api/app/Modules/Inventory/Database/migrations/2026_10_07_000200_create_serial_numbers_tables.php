<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per IMEI / serial the shop has had: where it is now (in stock in a branch, or gone).
        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->string('serial', 40);
            $table->string('status', 12)->comment('in_stock | out | damaged');
            $table->timestamps();

            $table->unique(['tenant_id', 'serial']);
            $table->index(['branch_id', 'variant_id', 'status']);
        });

        // What happened to it: received, sold, returned… Append-only.
        Schema::create('serial_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('serial_id')->constrained('serial_numbers')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('ref_type', 30)->nullable();
            $table->uuid('ref_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['serial_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_events');
        Schema::dropIfExists('serial_numbers');
    }
};
