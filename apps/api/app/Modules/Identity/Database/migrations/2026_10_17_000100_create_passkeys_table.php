<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passkeys (fingerprint / face on a phone or laptop) that unlock the app for their user.
        Schema::create('passkeys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // base64url, as the browser reports it.
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->smallInteger('alg');
            $table->bigInteger('sign_count')->default(0);
            $table->string('name', 120);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
