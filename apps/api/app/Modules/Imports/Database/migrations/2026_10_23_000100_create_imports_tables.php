<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The people an importer deals with: just contacts, no accounts or log-ins.
        Schema::create('import_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // supplier (factory / trader) | agent (buying agent) | shipping | customs (clearance)
            $table->string('type', 16);
            $table->string('name', 120);
            $table->string('country', 60)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('wechat', 60)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('notes', 1000)->nullable();
            // Piasters, EGP. > 0 = the shop owes them.
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        // Their statement. Append-only; the balance above moves with it under a row lock.
        Schema::create('import_contact_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('import_contacts')->cascadeOnDelete();
            // shipment | cost | payment | claim | reversal
            $table->string('type', 16);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('ref_type', 30)->nullable();
            $table->uuid('ref_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['contact_id', 'seq']);
        });

        Schema::create('import_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignUuid('contact_id')->constrained('import_contacts')->restrictOnDelete();
            // Where it's received into stock.
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            // ordered | shipped | customs | arrived | received | cancelled
            $table->string('status', 16);
            $table->date('ordered_on');
            $table->date('expected_on')->nullable();
            // How the shipment's costs are spread over its items: by value | by quantity.
            $table->string('allocation', 8)->default('value');
            // The deal in the supplier's currency, as a note only (e.g. "12,000 USD").
            $table->string('original_amount', 60)->nullable();
            $table->unsignedBigInteger('goods_total')->default(0);
            $table->unsignedBigInteger('costs_total')->default(0);
            $table->string('notes', 1000)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('received_by_name')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('import_shipment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shipment_id')->constrained('import_shipments')->cascadeOnDelete();
            $table->uuid('variant_id');
            $table->string('name', 255);
            $table->boolean('track_serial')->default(false);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price');
            // On receipt: good units into stock, damaged ones (not stocked); the rest are missing.
            $table->unsignedInteger('received_qty')->default(0);
            $table->unsignedInteger('damaged_qty')->default(0);
            $table->unsignedBigInteger('landed_unit_cost')->default(0);
            $table->jsonb('serials')->nullable();
        });

        Schema::create('import_shipment_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shipment_id')->constrained('import_shipments')->cascadeOnDelete();
            // shipping | customs | clearance | inland | agent | other
            $table->string('kind', 16);
            // Who it's owed to (posted to their statement); null = paid on the spot.
            $table->foreignUuid('contact_id')->nullable()->constrained('import_contacts')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('import_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('import_contacts')->restrictOnDelete();
            $table->foreignUuid('shipment_id')->nullable()->constrained('import_shipments')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            // bank | exchange (money transfer company) | agent | cash | wallet
            $table->string('method', 16);
            $table->date('paid_on');
            $table->string('received_by', 120)->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('proof', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_on']);
        });

        Schema::create('import_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shipment_id')->constrained('import_shipments')->cascadeOnDelete();
            // invoice | packing | bill_of_lading | photo | other
            $table->string('kind', 16);
            $table->string('name', 160);
            $table->string('path', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('uploaded_by_name')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['import_attachments', 'import_payments', 'import_shipment_costs', 'import_shipment_items', 'import_shipments', 'import_contact_transactions', 'import_contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
