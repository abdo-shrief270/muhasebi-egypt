<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted (APP_KEY) Base32 TOTP secret; set on setup, active once confirmed.
            $table->text('two_factor_secret')->nullable();
            // SHA-256 hashes of the unused one-time recovery codes.
            $table->jsonb('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Last accepted time step, so a code can't be replayed.
            $table->bigInteger('two_factor_last_step')->nullable();
        });

        // "Signed-in devices": where each token was issued.
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn(['ip_address', 'user_agent']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step',
        ]));
    }
};
