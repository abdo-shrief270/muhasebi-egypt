<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «في حاجة غلط في العرض ده»: from visitors, for the platform admins.
        Schema::create('market_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('offer_id', 120);
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('variant_id')->nullable();
            $table->string('title', 200)->nullable();
            $table->string('reason', 20);
            $table->string('note', 500)->nullable();
            $table->string('contact', 30)->nullable();
            $table->string('ip_hash', 64);
            $table->string('status', 12)->default('new');
            $table->string('resolution', 12)->nullable()->comment('dismissed | hidden');
            $table->string('resolved_by_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // Offers the platform admins took out of the market (after a report).
        Schema::create('market_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('variant_id');
            $table->string('reason', 200)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'variant_id']);
        });

        // The usual price of a catalog phone across shops (new), to catch a mistyped price.
        Schema::create('market_price_refs', function (Blueprint $table) {
            $table->uuid('item_id')->primary();
            $table->unsignedBigInteger('median');
            $table->unsignedInteger('shops');
            $table->timestamp('updated_at');
        });

        // The shop saying «السعر ده صح» for an offer the market held back (for that price only).
        Schema::create('market_price_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('variant_id');
            $table->unsignedBigInteger('price');
            $table->timestamps();
            $table->unique(['tenant_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_confirmations');
        Schema::dropIfExists('market_price_refs');
        Schema::dropIfExists('market_blocks');
        Schema::dropIfExists('market_reports');
    }
};
