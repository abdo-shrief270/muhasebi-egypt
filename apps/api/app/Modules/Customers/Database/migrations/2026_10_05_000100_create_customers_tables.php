<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable()->comment('E.164');
            $table->string('search_name', 160);
            $table->string('notes', 1000)->nullable();
            $table->bigInteger('credit_limit')->nullable()->comment('piasters; null = no limit');
            $table->bigInteger('balance')->default(0)->comment('piasters; > 0 = the customer owes the shop');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            // Postgres allows many NULLs in a unique index: customers without a phone don't clash.
            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'balance']);
        });
        DB::statement('create index customers_search_name_trgm on customers using gin (search_name gin_trgm_ops)');

        // The customer account: append-only.
        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount')->comment('piasters; + owes more, - owes less');
            $table->bigInteger('balance_after');
            $table->string('payment_method', 20)->nullable();
            $table->string('ref_type', 30)->nullable();
            $table->uuid('ref_id')->nullable();
            $table->string('reference', 30)->nullable()->comment('e.g. INV-000012, for the statement');
            $table->string('note', 500)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['customer_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
        Schema::dropIfExists('customers');
    }
};
