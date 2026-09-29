<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An erased customer's tickets keep no phone.
        Schema::table('repair_tickets', fn (Blueprint $table) => $table->string('customer_phone', 20)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('repair_tickets', fn (Blueprint $table) => $table->string('customer_phone', 20)->nullable(false)->change());
    }
};
