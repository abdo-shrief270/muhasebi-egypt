<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shop's wallet: credit (piasters, taken off its next subscription payment) and points
        // (earned, turned into credit). Who invited it, and whether that shop got its points.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->bigInteger('credit_balance')->default(0);
            $table->integer('points_balance')->default(0);
            $table->uuid('referred_by')->nullable();
            $table->timestamp('referral_rewarded_at')->nullable();
        });

        // Every move of credit or points. Append-only; the balances above move with it under a row lock.
        Schema::create('billing_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // credit | points
            $table->string('unit', 8);
            // referral | early_renewal | yearly | onboarding | convert | payment | refund | coupon | admin
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('ref_type', 30)->nullable();
            $table->string('ref_id', 64)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['tenant_id', 'seq']);
            $table->index(['tenant_id', 'type']);
        });

        // Subscription coupons, made by the platform admins.
        Schema::create('billing_coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            // percent (value %, for `months` months of subscription) | amount (piasters off the next payment) | credit (piasters into the wallet)
            $table->string('kind', 8);
            $table->unsignedBigInteger('value');
            $table->unsignedSmallInteger('months')->default(1);
            // Only for shops that never paid.
            $table->boolean('new_shops_only')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        // A discount a shop holds (a coupon it entered, or the referral welcome) until its payments use it up.
        Schema::create('billing_coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('coupon_id')->nullable();
            // coupon | referral
            $table->string('source', 10);
            $table->string('code', 32);
            $table->string('kind', 8);
            $table->unsignedBigInteger('value');
            // Months of subscription still discounted (percent); 1 → 0 for an amount.
            $table->unsignedSmallInteger('months_left');
            $table->timestamp('created_at');
            $table->timestamp('used_up_at')->nullable();

            $table->unique(['tenant_id', 'coupon_id']);
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('credit_used')->default(0);
            $table->uuid('redemption_id')->nullable();
        });
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->bigInteger('credit_used')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', fn (Blueprint $table) => $table->dropColumn('credit_used'));
        Schema::table('payment_requests', fn (Blueprint $table) => $table->dropColumn(['discount', 'credit_used', 'redemption_id']));
        Schema::dropIfExists('billing_coupon_redemptions');
        Schema::dropIfExists('billing_coupons');
        Schema::dropIfExists('billing_wallet_transactions');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['credit_balance', 'points_balance', 'referred_by', 'referral_rewarded_at']));
    }
};
