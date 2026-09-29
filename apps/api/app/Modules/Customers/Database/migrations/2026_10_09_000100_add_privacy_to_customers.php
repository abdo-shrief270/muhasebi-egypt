<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personal Data Protection Law 151/2020: the customer's consent to keeping their data, and erasure.
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('data_consent')->nullable()->comment('null = not asked (added before consent was recorded)');
            $table->timestamp('data_consent_at')->nullable();
            $table->uuid('data_consent_by')->nullable();
            $table->string('data_consent_by_name')->nullable();
            $table->timestamp('erased_at')->nullable()->comment('personal data anonymised; financial records kept');
        });

        // One row per shop: erase customers with no activity for this many years (null = never).
        Schema::create('customer_privacy_settings', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('retention_years')->nullable();
            $table->string('updated_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_privacy_settings');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['data_consent', 'data_consent_at', 'data_consent_by', 'data_consent_by_name', 'erased_at']);
        });
    }
};
