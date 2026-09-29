<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('status', 30);
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            // The device
            $table->unsignedBigInteger('device_model_id')->nullable()->comment('catalog device_models, when picked from the list');
            $table->string('device_name', 160);
            $table->string('imei', 40)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('unlock_type', 20)->default('none')->comment('none | pin | pattern | password');
            $table->text('unlock_code')->nullable()->comment('encrypted');
            $table->json('accessories');
            $table->json('condition')->comment('visible damage ticked at intake');
            $table->json('checks')->comment('what worked at intake: key => yes | no | unknown');
            // Faults: what the customer said, then what the technician found
            $table->json('reported_faults');
            $table->string('reported_note', 1000)->nullable();
            $table->json('diagnosed_faults')->nullable();
            $table->string('diagnosis_note', 1000)->nullable();
            // People and dates
            $table->uuid('received_by')->nullable();
            $table->string('received_by_name')->nullable();
            $table->timestamp('received_at', 6);
            $table->timestamp('expected_at')->nullable()->comment('promised to the customer');
            $table->uuid('technician_id')->nullable();
            $table->string('technician_name')->nullable();
            $table->timestamp('ready_at', 6)->nullable();
            $table->timestamp('delivered_at', 6)->nullable();
            $table->uuid('delivered_by')->nullable();
            $table->string('delivered_by_name')->nullable();
            // Money (piasters)
            $table->unsignedBigInteger('estimate')->nullable()->comment('quoted at intake');
            $table->unsignedBigInteger('labor')->default(0);
            $table->unsignedBigInteger('parts_total')->default(0);
            $table->unsignedBigInteger('parts_cost')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0)->comment('labor + parts - discount');
            $table->bigInteger('paid')->default(0)->comment('money taken, less refunds');
            $table->unsignedBigInteger('credit')->default(0)->comment('put on the customer account');
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->timestamp('warranty_until')->nullable();
            $table->foreignUuid('warranty_of_id')->nullable()->comment('the ticket this one came back under warranty from');
            $table->string('public_token', 40)->unique();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['branch_id', 'received_at']);
            $table->index(['customer_id', 'received_at']);
        });
        Schema::table('repair_tickets', function (Blueprint $table) {
            $table->foreign('warranty_of_id')->references('id')->on('repair_tickets')->nullOnDelete();
        });

        Schema::create('repair_ticket_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('repair_tickets')->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_cost')->comment('FIFO cost of what left stock');
            $table->uuid('added_by')->nullable();
            $table->string('added_by_name')->nullable();
            $table->timestamps();
        });

        Schema::create('repair_ticket_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('repair_tickets')->cascadeOnDelete();
            $table->string('kind', 20)->comment('deposit | payment | refund');
            $table->string('method', 20);
            $table->bigInteger('amount')->comment('piasters; refunds are negative');
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
        });

        // The ticket's story: every status change, note, part and payment. Append-only.
        Schema::create('repair_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('repair_tickets')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('note', 1000)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['ticket_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_ticket_events');
        Schema::dropIfExists('repair_ticket_payments');
        Schema::dropIfExists('repair_ticket_parts');
        Schema::dropIfExists('repair_tickets');
    }
};
