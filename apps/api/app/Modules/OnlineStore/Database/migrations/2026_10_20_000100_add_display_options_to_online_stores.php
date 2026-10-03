<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the store shows: the owner shapes it (all on = how stores looked so far).
        Schema::table('online_stores', function (Blueprint $table) {
            $table->boolean('show_prices')->default(true);
            $table->boolean('show_models')->default(true);
            $table->boolean('show_latest')->default(true);
            $table->boolean('show_whatsapp')->default(true);
            $table->string('announcement', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table) {
            $table->dropColumn(['show_prices', 'show_models', 'show_latest', 'show_whatsapp', 'announcement']);
        });
    }
};
