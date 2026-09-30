<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who sold the shop a device. One row per national ID (found again by its keyed hash).
        Schema::create('used_device_sellers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable()->comment('E.164');
            $table->text('national_id')->nullable()->comment('encrypted');
            $table->string('national_id_hash', 64)->nullable()->comment('keyed hash, for lookup');
            $table->date('birth_date')->nullable();
            $table->string('gender', 6)->nullable();
            $table->string('governorate', 40)->nullable();
            $table->timestamp('erased_at')->nullable()->comment('name and phone anonymised');
            $table->timestamp('id_purged_at')->nullable()->comment('national ID and card photos deleted after the retention period');
            $table->timestamps();

            $table->unique(['tenant_id', 'national_id_hash']);
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('used_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->foreignUuid('seller_id')->constrained('used_device_sellers')->restrictOnDelete();
            $table->unsignedBigInteger('device_model_id')->nullable()->comment('the shop\'s model list (Catalog)');
            $table->string('model_name', 120);
            $table->string('storage', 20)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('imei', 20);
            $table->string('imei2', 20)->nullable();
            $table->string('grade', 1);
            $table->jsonb('checklist');
            $table->unsignedTinyInteger('battery_health')->nullable()->comment('%');
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('purchase_price')->comment('piasters');
            $table->unsignedBigInteger('asking_price')->comment('piasters');
            $table->string('payment_method', 20);
            $table->uuid('variant_id')->comment('the sellable unit (Catalog)');
            $table->string('status', 20)->default('in_stock');
            $table->uuid('sale_id')->nullable();
            $table->string('sale_reference', 20)->nullable();
            $table->unsignedBigInteger('sale_price')->nullable()->comment('after the invoice discount');
            $table->timestamp('sold_at')->nullable();
            $table->uuid('bought_by')->nullable();
            $table->string('bought_by_name')->nullable();
            $table->timestamp('bought_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'imei']);
            $table->index(['tenant_id', 'status', 'bought_at']);
            $table->index('variant_id');
            $table->index('seller_id');
        });

        // Card and device photos: encrypted files on the private disk, served only through the API.
        Schema::create('used_device_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('used_device_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->comment('id_front | id_back | device');
            $table->string('path');
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->timestamp('created_at');

            $table->index(['used_device_id', 'kind']);
        });

        Schema::create('used_device_settings', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('id_retention_years')->default(3);
            $table->string('updated_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_device_settings');
        Schema::dropIfExists('used_device_photos');
        Schema::dropIfExists('used_devices');
        Schema::dropIfExists('used_device_sellers');
    }
};
