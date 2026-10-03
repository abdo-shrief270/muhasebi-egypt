<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Shown on the shop's online store (when it has one); unticked = hidden there.
            $table->boolean('online_visible')->default(true);
            // What the customer reads on the store (notes stay internal).
            $table->text('online_description')->nullable();
        });

        // Product photos: each stored as WebP in three widths (ProductImages::SIZES).
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('sort')->default(0);
            $table->smallInteger('width');
            $table->smallInteger('height');
            $table->timestamps();
            $table->index(['product_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['online_visible', 'online_description']));
    }
};
