<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IMEIs / serials of the units on the line (products that track them).
        Schema::table('purchase_items', fn (Blueprint $table) => $table->jsonb('serials')->nullable());
        Schema::table('purchase_return_items', fn (Blueprint $table) => $table->jsonb('serials')->nullable());
    }

    public function down(): void
    {
        Schema::table('purchase_items', fn (Blueprint $table) => $table->dropColumn('serials'));
        Schema::table('purchase_return_items', fn (Blueprint $table) => $table->dropColumn('serials'));
    }
};
