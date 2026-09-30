<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per user: the «ابدأ من هنا» card folded or hidden. The steps themselves come from the shop's data.
        Schema::create('onboarding_preferences', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('collapsed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_preferences');
    }
};
