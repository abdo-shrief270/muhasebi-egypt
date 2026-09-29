<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cashier's drawer session in a branch: opened with the cash in it, closed with what was counted.
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->uuid('user_id');
            $table->string('user_name');
            $table->unsignedBigInteger('opening_cash');
            $table->timestamp('opened_at', 6);
            $table->timestamp('closed_at', 6)->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('closed_by_name')->nullable();
            $table->json('expected')->nullable()->comment('per payment method at close, piasters');
            $table->json('counted')->nullable()->comment('per payment method at close, piasters');
            $table->bigInteger('cash_difference')->nullable()->comment('counted - expected cash; < 0 = short');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['branch_id', 'opened_at']);
        });
        // One open shift per cashier per branch.
        DB::statement('create unique index cash_shifts_one_open on cash_shifts (branch_id, user_id) where closed_at is null');

        // Every amount that went into or out of a drawer (or was taken by card / wallet during a shift).
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('shift_id')->nullable()->constrained('cash_shifts')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('method', 20);
            $table->bigInteger('amount')->comment('piasters; + in, - out');
            $table->string('category', 30)->nullable()->comment('expenses');
            $table->string('ref_type', 30)->nullable();
            $table->uuid('ref_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['shift_id', 'seq']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_shifts');
    }
};
