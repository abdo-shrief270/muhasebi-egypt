<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Partners (برنامج الشركاء): people, not shops, who bring shops with their link and earn a share of what those shops pay.
        Schema::create('affiliates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('code', 20)->unique();
            $table->string('status', 12)->default('active'); // active | suspended
            $table->string('payout_method', 12)->nullable(); // instapay | wallet | bank
            $table->string('payout_account', 80)->nullable();
            $table->string('payout_name', 120)->nullable();
            $table->unsignedSmallInteger('rate_bp')->nullable()->comment('own share in basis points; null = billing.affiliates.rate_percent');
            $table->string('channel', 120)->nullable()->comment('where they promote: a page, a channel…');
            $table->unsignedInteger('clicks')->default(0);
            $table->text('admin_note')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Which shop came through which partner (one partner per shop, kept for good).
        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('first_paid_at')->nullable();
            $table->timestamp('commission_until')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 12);
            $table->string('account', 80);
            $table->string('account_name', 120)->nullable();
            $table->string('status', 12)->default('requested'); // requested | paid | rejected
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // The partner's share of each real payment by a shop they brought.
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->unique()->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('invoice_reference', 30);
            $table->unsignedBigInteger('base')->comment('what the shop paid, without VAT or credit, piasters');
            $table->unsignedSmallInteger('rate_bp');
            $table->unsignedBigInteger('amount');
            $table->string('status', 12)->default('pending'); // pending (held) | paid | void
            $table->timestamp('available_at')->comment('after the hold, it can be paid out');
            $table->foreignUuid('payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliates');
    }
};
