<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Whether shops may sign up on their own (one row, set by the platform admins; none = config).
    public function up(): void
    {
        Schema::create('signup_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->boolean('open');
            $table->string('whatsapp', 20)->nullable()->comment('E.164; where closed sign-up sends people');
            $table->string('message', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_settings');
    }
};
