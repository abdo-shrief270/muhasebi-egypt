<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_tickets', function (Blueprint $table) {
            // Sent out: this shop's device, repaired at a partner shop (a "شغل صيانة" shop order).
            $table->uuid('outsourced_order_id')->nullable()->index();
            $table->string('outsourced_reference', 20)->nullable();
            $table->string('outsourced_shop')->nullable();
            $table->string('outsourced_status', 16)->nullable();
            $table->bigInteger('outsource_cost')->default(0)->comment('piasters, the partner\'s price');

            // Taken in for a partner: the device came in a partner's repair order.
            $table->uuid('partner_order_id')->nullable()->index();
            $table->unsignedBigInteger('partner_item_id')->nullable();
            $table->string('partner_reference', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repair_tickets', function (Blueprint $table) {
            $table->dropColumn(['outsourced_order_id', 'outsourced_reference', 'outsourced_shop', 'outsourced_status', 'outsource_cost', 'partner_order_id', 'partner_item_id', 'partner_reference']);
        });
    }
};
