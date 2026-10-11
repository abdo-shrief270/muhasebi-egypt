<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prices platform admins edit (apps/admin «الأسعار»): a row overrides config/billing.php for one plan or module.
        Schema::create('billing_catalog', function (Blueprint $table) {
            $table->string('kind', 10)->comment('plan | module');
            $table->string('key', 40);
            $table->string('name', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->bigInteger('monthly')->comment('piasters, VAT included');
            $table->bigInteger('lifetime')->default(0)->comment('one-time price; 0 = not sold for life');
            $table->bigInteger('maintenance')->default(0)->comment('yearly, after the lifetime price');
            $table->boolean('enabled')->default(true);
            $table->boolean('featured')->default(false);
            $table->jsonb('modules')->nullable()->comment('plans: the modules they include');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->primary(['kind', 'key']);
        });

        // Platform-wide billing switches (one row): yearly subscriptions and the lifetime option.
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->boolean('yearly_enabled')->default(false);
            $table->unsignedSmallInteger('yearly_months')->default(10);
            $table->boolean('lifetime_enabled')->default(false);
            $table->string('lifetime_name', 80);
            $table->string('lifetime_tagline', 255)->nullable();
            $table->jsonb('lifetime_installments');
            $table->jsonb('lifetime_perks')->default('[]');
            $table->unsignedSmallInteger('lifetime_affiliate_rate_percent')->nullable();
            $table->boolean('lifetime_allow_coupons')->default(false);
            $table->unsignedSmallInteger('launch_discount_percent')->default(0)->comment('off the lifetime price for the first buyers');
            $table->unsignedInteger('launch_seats')->nullable();
            $table->timestamp('launch_ends_at')->nullable();
            $table->timestamps();
        });

        // «باقة المؤسسين» becomes «مدى الحياة»: same columns, new names; no separate offer table.
        Schema::dropIfExists('billing_founder_offer');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('founder_number', 'lifetime_number');
            $table->renameColumn('founder_since', 'lifetime_since');
            $table->renameColumn('founder_plan', 'lifetime_plan');
            $table->renameColumn('founder_price', 'lifetime_price');
            $table->renameColumn('founder_paid', 'lifetime_paid');
            $table->renameColumn('founder_installments', 'lifetime_installments');
            $table->renameColumn('founder_annual_fee', 'lifetime_maintenance');
        });
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('founder_module_discount'));
        Schema::table('payment_requests', fn (Blueprint $table) => $table->renameColumn('founder', 'lifetime'));
        foreach (['subscriptions', 'payment_requests', 'billing_invoices'] as $table) {
            DB::table($table)->where('cycle', 'founder')->update(['cycle' => 'lifetime']);
        }
    }

    public function down(): void
    {
        foreach (['subscriptions', 'payment_requests', 'billing_invoices'] as $table) {
            DB::table($table)->where('cycle', 'lifetime')->update(['cycle' => 'founder']);
        }
        Schema::table('payment_requests', fn (Blueprint $table) => $table->renameColumn('lifetime', 'founder'));
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('lifetime_number', 'founder_number');
            $table->renameColumn('lifetime_since', 'founder_since');
            $table->renameColumn('lifetime_plan', 'founder_plan');
            $table->renameColumn('lifetime_price', 'founder_price');
            $table->renameColumn('lifetime_paid', 'founder_paid');
            $table->renameColumn('lifetime_installments', 'founder_installments');
            $table->renameColumn('lifetime_maintenance', 'founder_annual_fee');
            $table->unsignedSmallInteger('founder_module_discount')->default(0);
        });
        Schema::create('billing_founder_offer', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('name', 80);
            $table->string('tagline', 255)->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('plans');
            $table->jsonb('installments');
            $table->jsonb('perks')->default('[]');
            $table->unsignedSmallInteger('module_discount_percent')->default(0);
            $table->unsignedSmallInteger('affiliate_rate_percent')->nullable();
            $table->boolean('allow_coupons')->default(false);
            $table->timestamps();
        });
        Schema::dropIfExists('billing_settings');
        Schema::dropIfExists('billing_catalog');
    }
};
