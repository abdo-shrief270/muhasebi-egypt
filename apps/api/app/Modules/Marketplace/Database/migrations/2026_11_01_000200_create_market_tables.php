<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a shop keeps out of «سوق محاسبي». No row = everything listed (every plan includes it).
        Schema::create('market_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('listed')->default(true);
            $table->jsonb('hidden_branches')->default('[]');
            $table->jsonb('hidden_categories')->default('[]');
            $table->jsonb('hidden_products')->default('[]');
            $table->timestamps();
        });

        // Where the incremental sync got to (one row per job).
        Schema::create('market_sync_marks', function (Blueprint $table) {
            $table->string('name', 40)->primary();
            $table->timestamp('at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_sync_marks');
        Schema::dropIfExists('market_settings');
    }
};
