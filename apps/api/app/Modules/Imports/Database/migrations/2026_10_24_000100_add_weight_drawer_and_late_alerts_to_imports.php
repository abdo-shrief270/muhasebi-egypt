<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The line's total weight in grams, for spreading the costs by weight (allocation = weight).
        Schema::table('import_shipment_items', function (Blueprint $table) {
            $table->unsignedBigInteger('weight')->nullable();
        });

        // The expected date the "late" alert went out for: once per date, again if it moves.
        Schema::table('import_shipments', function (Blueprint $table) {
            $table->date('late_alerted_for')->nullable();
        });

        // Cash paid out of a shift drawer (which branch), so a reversal puts it back.
        Schema::table('import_payments', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable();
            $table->boolean('from_drawer')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('import_payments', fn (Blueprint $table) => $table->dropColumn(['branch_id', 'from_drawer']));
        Schema::table('import_shipments', fn (Blueprint $table) => $table->dropColumn('late_alerted_for'));
        Schema::table('import_shipment_items', fn (Blueprint $table) => $table->dropColumn('weight'));
    }
};
