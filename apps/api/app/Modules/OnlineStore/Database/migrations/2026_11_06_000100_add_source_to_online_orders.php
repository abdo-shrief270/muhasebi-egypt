<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where the order came from: the shop's own store, or «سوق محاسبي» (then from the branch the offer was in).
        Schema::table('online_orders', fn (Blueprint $table) => $table->string('source', 10)->default('store'));
    }

    public function down(): void
    {
        Schema::table('online_orders', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
