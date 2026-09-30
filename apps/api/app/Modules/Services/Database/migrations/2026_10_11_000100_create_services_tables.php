<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shop's mobile wallets and airtime (رصيد) lines, per branch. The balance moves only through
        // Services\Support\ServiceLedger, in step with service_transactions.
        Schema::create('service_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20)->comment('wallet | airtime');
            $table->string('provider', 20);
            $table->string('name', 80);
            $table->string('phone', 20)->nullable()->comment('the line / wallet number, as typed');
            $table->bigInteger('balance')->default(0)->comment('piasters');
            $table->bigInteger('cost_value')->default(0)->comment('what the balance cost the shop, piasters (airtime bought at a discount)');
            $table->unsignedBigInteger('daily_limit')->nullable()->comment('warn when the day\'s transfers pass it');
            $table->string('withdraw_fee_mode', 10)->default('cash')->comment('cash: fee off the cash handed out | wallet: the customer sends it on top');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'name']);
        });

        // The fee suggested for an operation on an account.
        Schema::create('service_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('service_accounts')->cascadeOnDelete();
            $table->string('operation', 20)->comment('deposit | withdraw | topup');
            $table->unsignedInteger('percent')->default(0)->comment('basis points (150 = 1.5%)');
            $table->unsignedBigInteger('fixed')->default(0)->comment('piasters, added to the percent');
            $table->unsignedBigInteger('min')->default(0);
            $table->unsignedBigInteger('max')->nullable();
            $table->unsignedInteger('round_to')->default(0)->comment('round the fee up to a multiple of this many piasters');
            $table->timestamps();

            $table->unique(['account_id', 'operation']);
        });

        // Every operation and balance move on an account: append-only. A correction is a new row with
        // reverses_id set and every amount negated, never an edit.
        Schema::create('service_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('account_id')->constrained('service_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('type', 20)->comment('opening | deposit | withdraw | topup | fund | cash_out');
            $table->bigInteger('amount')->comment('the customer\'s amount / face value / money moved, piasters; negative on a reversal');
            $table->bigInteger('fee')->default(0);
            $table->bigInteger('suggested_fee')->nullable();
            $table->bigInteger('balance_change');
            $table->bigInteger('balance_after');
            $table->bigInteger('cost_change');
            $table->bigInteger('cost_after');
            $table->bigInteger('cash')->default(0)->comment('into (+) / out of (-) the user\'s drawer');
            $table->bigInteger('profit')->default(0)->comment('the fee, plus the airtime margin on a top-up');
            $table->string('fee_mode', 10)->nullable();
            $table->string('source', 10)->nullable()->comment('fund / cash-out: drawer | safe');
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('reverses_id')->nullable()->unique();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->unique(['tenant_id', 'number']);
            $table->index(['account_id', 'seq']);
            $table->index(['tenant_id', 'branch_id', 'created_at']);
        });
        Schema::table('service_transactions', function (Blueprint $table) {
            $table->foreign('reverses_id')->references('id')->on('service_transactions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_transactions');
        Schema::dropIfExists('service_fee_rules');
        Schema::dropIfExists('service_accounts');
    }
};
