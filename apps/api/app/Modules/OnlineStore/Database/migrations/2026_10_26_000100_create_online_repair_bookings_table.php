<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «احجز صيانة» on the store (needs the repairs module), with a line for the customer.
        Schema::table('online_stores', function (Blueprint $table) {
            $table->boolean('repair_booking')->default(false);
            $table->string('repair_booking_note', 255)->nullable();
        });

        // A customer asking to bring a device in; becomes a repair ticket when it arrives.
        Schema::create('online_repair_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            // new | contacted | converted | cancelled
            $table->string('status', 16);
            $table->uuid('customer_id')->nullable();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->string('device', 120);
            $table->string('problem', 1000);
            $table->date('preferred_on')->nullable();
            $table->boolean('consent')->nullable();
            $table->uuid('ticket_id')->nullable();
            $table->string('ticket_reference', 20)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->string('handled_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_repair_bookings');
        Schema::table('online_stores', fn (Blueprint $table) => $table->dropColumn(['repair_booking', 'repair_booking_note']));
    }
};
