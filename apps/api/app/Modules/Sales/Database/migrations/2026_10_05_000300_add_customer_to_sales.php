<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->nullable()->after('price_level')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('credit')->default(0)->after('paid')->comment('put on the customer account (آجل)');
            $table->index(['customer_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('credit');
        });
    }
};
