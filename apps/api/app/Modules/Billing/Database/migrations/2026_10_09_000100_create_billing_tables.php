<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The people who run the platform (not shop staff): approve payments, activate shops.
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // One per shop. Paid (or on trial) until paid_until; the status follows from the dates.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 30)->nullable()->comment('null while on trial');
            $table->string('cycle', 10)->nullable()->comment('monthly | yearly');
            $table->jsonb('modules')->default('[]')->comment('extra modules on top of the plan');
            $table->boolean('on_trial')->default(true);
            $table->timestamp('paid_until');
            $table->timestamp('suspended_at')->nullable()->comment('suspended by an admin, whatever the dates');
            $table->string('suspended_reason')->nullable();
            $table->timestamps();

            $table->index('paid_until');
        });

        // An owner's InstaPay transfer waiting for an admin to check it.
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->string('cycle', 10);
            $table->jsonb('modules')->default('[]');
            $table->bigInteger('amount')->comment('piasters, what the owner should have sent');
            $table->string('method', 20)->default('instapay');
            $table->string('reference', 60)->comment('the transfer reference');
            $table->string('sender_name', 120)->nullable();
            $table->string('sender_phone', 20)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 12)->default('pending')->comment('pending | approved | rejected | cancelled');
            $table->uuid('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // What the platform billed the shop for a paid period. Numbered across the platform.
        DB::statement('create sequence if not exists billing_invoice_number_seq');
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number')->unique();
            $table->string('plan', 30);
            $table->string('cycle', 10);
            $table->unsignedSmallInteger('months');
            $table->jsonb('lines')->comment('[{description, amount}]');
            $table->bigInteger('total')->comment('piasters, VAT included');
            $table->bigInteger('vat');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('method', 20)->comment('instapay | manual');
            $table->string('payment_reference', 60)->nullable();
            $table->string('issued_by_name')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['tenant_id', 'number']);
        });

        // Shops that existed before billing start a fresh trial.
        $trialEnd = now()->addDays((int) config('billing.trial_days', 14));
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'modules' => '[]',
                'on_trial' => true,
                'paid_until' => $trialEnd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
        DB::statement('drop sequence if exists billing_invoice_number_seq');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('platform_admins');
    }
};
