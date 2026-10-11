<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «باقة المؤسسين»: one row, edited by platform admins (prices, seats, dates, perks…). Off until they open it.
        Schema::create('billing_founder_offer', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('name', 80);
            $table->string('tagline', 255)->nullable();
            $table->unsignedInteger('seats')->nullable()->comment('null = no limit');
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('plans')->comment('{plan: {enabled, price, annual_fee}} piasters, VAT included');
            $table->jsonb('installments')->comment('allowed counts, each covering 12/N months of the first year');
            $table->jsonb('perks')->default('[]');
            $table->unsignedSmallInteger('module_discount_percent')->default(0);
            $table->unsignedSmallInteger('affiliate_rate_percent')->nullable()->comment('partners\' share on founder payments; null = their usual rate');
            $table->boolean('allow_coupons')->default(false);
            $table->timestamps();
        });

        // A shop's founder terms, locked when its first founder payment is approved.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('founder_number')->nullable()->unique();
            $table->timestamp('founder_since')->nullable();
            $table->string('founder_plan', 30)->nullable();
            $table->bigInteger('founder_price')->nullable();
            $table->bigInteger('founder_paid')->default(0);
            $table->unsignedSmallInteger('founder_installments')->nullable();
            $table->bigInteger('founder_annual_fee')->nullable();
            $table->unsignedSmallInteger('founder_module_discount')->default(0);
        });

        // The founder terms a request was priced with (approval prices it the same way).
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->jsonb('founder')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', fn (Blueprint $table) => $table->dropColumn('founder'));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn([
            'founder_number', 'founder_since', 'founder_plan', 'founder_price', 'founder_paid', 'founder_installments', 'founder_annual_fee', 'founder_module_discount',
        ]));
        Schema::dropIfExists('billing_founder_offer');
    }
};
