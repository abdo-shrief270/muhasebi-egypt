<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How long a part is guaranteed once fitted in a repair (the ticket's default for it).
        Schema::table('products', fn (Blueprint $table) => $table->unsignedSmallInteger('warranty_days')->nullable());
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('warranty_days'));
    }
};
