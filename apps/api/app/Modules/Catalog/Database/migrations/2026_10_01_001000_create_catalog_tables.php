<?php

use App\Modules\Catalog\Support\DefaultCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists pg_trgm');

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('search_name')->comment('normalised "brand model", see SearchText');
            $table->timestamps();

            $table->unique(['tenant_id', 'brand_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('search_name')->comment('normalised name, see SearchText');
            $table->string('sku', 64)->nullable();
            $table->boolean('track_serial')->default(false)->comment('each unit has its own IMEI / serial');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'category_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable()->comment('colour / capacity, e.g. "أسود 128GB"');
            $table->string('quality_grade', 20)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedBigInteger('price_retail')->default(0)->comment('piasters');
            $table->unsignedBigInteger('price_wholesale')->nullable()->comment('piasters');
            $table->unsignedBigInteger('price_technician')->nullable()->comment('piasters');
            $table->unsignedBigInteger('price_online')->nullable()->comment('piasters');
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'barcode']);
            $table->index('product_id');
        });

        // Which phone models a product fits (a screen protector for iPhone 13 and 14, ...).
        Schema::create('device_model_product', function (Blueprint $table) {
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_model_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'device_model_id']);
            $table->index('device_model_id');
        });

        DB::statement('create index products_search_name_trgm on products using gin (search_name gin_trgm_ops)');
        DB::statement('create index products_sku_trgm on products using gin (lower(sku) gin_trgm_ops)');
        DB::statement('create index product_variants_barcode_trgm on product_variants using gin (barcode gin_trgm_ops)');
        DB::statement('create index device_models_search_name_trgm on device_models using gin (search_name gin_trgm_ops)');

        // Shops that registered before the catalog existed get the starter catalog too.
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DefaultCatalog::seed((string) $tenantId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_model_product');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('device_models');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
