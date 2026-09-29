<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform admins must sign in with an authenticator code too (set up from the server's shell).
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->comment('encrypted');
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->comment('a code works once');
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', fn (Blueprint $table) => $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step']));
    }
};
