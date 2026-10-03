<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a sale came from, when not the counter (e.g. online_order + the order's id).
        Schema::table('sales', function (Blueprint $table) {
            $table->string('origin_type', 30)->nullable();
            $table->uuid('origin_id')->nullable();

            $table->index(['origin_type', 'origin_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['origin_type', 'origin_id']);
            $table->dropColumn(['origin_type', 'origin_id']);
        });
    }
};
