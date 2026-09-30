<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The owner's choice for a feature switch; no row = the feature's default.
        Schema::create('tenant_features', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->boolean('enabled');
            $table->string('updated_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_features');
    }
};
