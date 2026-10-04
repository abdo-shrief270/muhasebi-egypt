<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform admin's say over every shop: a module's status (live / «قريباً» / hidden / free for
 * everyone), its trial, and a feature switch's default or a value forced on every shop. A missing row
 * = what the module's manifest says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_modules', function (Blueprint $table): void {
            $table->string('module_key', 64)->primary();
            $table->string('status', 16)->nullable();          // live | coming_soon | hidden | free
            $table->boolean('trial_allowed')->nullable();
            $table->boolean('auto_trial')->nullable();          // starts on trial when a shop of its types registers
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->json('shop_types')->nullable();
            $table->string('name', 80)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('updated_by_name', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('platform_features', function (Blueprint $table): void {
            $table->string('feature_key', 80)->primary();
            $table->string('mode', 8)->default('shop');         // shop (the owner decides) | on | off (every shop)
            $table->boolean('default')->nullable();             // what a shop starts with, while mode = shop
            $table->string('updated_by_name', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_features');
        Schema::dropIfExists('platform_modules');
    }
};
