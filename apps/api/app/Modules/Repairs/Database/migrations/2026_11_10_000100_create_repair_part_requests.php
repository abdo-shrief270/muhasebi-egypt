<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A technician asks the store for a part; whoever hands it out puts it on the ticket.
        Schema::create('repair_part_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('ticket_id')->constrained('repair_tickets')->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty');
            $table->string('note', 190)->nullable();
            $table->string('status', 20)->default('requested')->comment('requested | handed | rejected | cancelled');
            $table->uuid('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->uuid('handled_by')->nullable();
            $table->string('handled_by_name')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->string('reason', 190)->nullable()->comment('why it was refused');
            $table->unsignedBigInteger('part_id')->nullable()->comment('the repair_ticket_parts row it became');
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_part_requests');
    }
};
