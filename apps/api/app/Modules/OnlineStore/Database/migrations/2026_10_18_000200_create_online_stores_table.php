<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One online store per shop: store.muhasebi.com/{slug}.
        Schema::create('online_stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug', 40)->unique();
            // off = closed; whatsapp = the cart goes to the shop as a WhatsApp message; orders = saved orders (later).
            $table->string('mode', 16)->default('off');
            $table->string('name', 120);
            $table->string('tagline', 160)->nullable();
            $table->text('about')->nullable();
            $table->string('color', 7)->default('#0f766e');
            // Whose stock the store shows.
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('whatsapp', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('map_url', 500)->nullable();
            $table->string('hours', 255)->nullable();
            $table->text('policy')->nullable();
            $table->string('facebook', 255)->nullable();
            $table->string('instagram', 255)->nullable();
            $table->boolean('show_out_of_stock')->default(true);
            $table->boolean('show_quantity')->default(false);
            // Logo / cover: WebP files on the local disk (null = none); the value changes with each upload.
            $table->string('logo', 64)->nullable();
            $table->string('cover', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_stores');
    }
};
