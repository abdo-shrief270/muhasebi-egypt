<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_ticket_parts', function (Blueprint $table) {
            $table->jsonb('serials')->nullable()->comment('IMEIs / serials of the units fitted, for products that track them');
        });
    }

    public function down(): void
    {
        Schema::table('repair_ticket_parts', function (Blueprint $table) {
            $table->dropColumn('serials');
        });
    }
};
