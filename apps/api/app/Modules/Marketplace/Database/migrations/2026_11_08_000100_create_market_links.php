<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the shop said a phone it sells is in the reference catalog (no row = the automatic
        // match from its name; a row with no phone = «مش في الكتالوج»).
        Schema::create('market_links', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('variant_id');
            $table->foreignUuid('phone_id')->nullable()->constrained('market_phones')->cascadeOnDelete();
            $table->smallInteger('storage_gb')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'variant_id']);
            $table->index('phone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_links');
    }
};
