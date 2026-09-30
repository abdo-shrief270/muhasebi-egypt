<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A return note (إذن مرتجع): units going back to one source (a supplier, or a partner shop).
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('source_type', 12)->comment('supplier | shop');
            $table->uuid('source_id')->comment('supplier id, or the partner shop tenant id');
            $table->string('source_name');
            $table->string('source_phone', 20)->nullable();
            $table->string('status', 20)->comment('pending | sent | accepted | partially_accepted | rejected | cancelled');
            $table->unsignedInteger('units')->default(0);
            $table->unsignedBigInteger('total_cost')->default(0)->comment('piasters');
            $table->unsignedBigInteger('accepted_value')->default(0);
            $table->unsignedBigInteger('rejected_value')->default(0);
            $table->string('resolution', 12)->nullable()->comment('credit | refund | replacement');
            $table->string('refund_method', 20)->nullable();
            $table->string('rejected_action', 12)->nullable()->comment('restock | write_off');
            $table->string('notes', 1000)->nullable();
            $table->string('settle_note', 500)->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->string('settled_by_name')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        // The returns bin: units out of sellable stock, waiting to go back to where they came from.
        // A row is on a note once one takes it, and settled when the source accepted / refused it.
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('supplier_return_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('variant_name');
            $table->unsignedInteger('qty');
            $table->string('serial', 40)->nullable()->comment('one unit per row for serial-tracked products');
            $table->unsignedBigInteger('unit_cost')->comment('piasters');
            $table->uuid('lot_id')->nullable()->comment('the stock lot the unit left');
            $table->string('source_type', 12)->nullable()->comment('supplier | shop; null = not known yet');
            $table->uuid('source_id')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_doc', 40)->nullable()->comment('the purchase / order it came on, e.g. PUR-00012');
            $table->string('detected_by', 8)->nullable()->comment('serial | lot | manual');
            $table->string('reason', 20);
            $table->string('note', 500)->nullable();
            $table->string('origin', 12)->comment('manual | sale_return | repair');
            $table->uuid('origin_id')->nullable();
            $table->string('origin_label', 80)->nullable();
            $table->string('status', 12)->comment('in_bin | on_note | settled');
            $table->unsignedInteger('accepted_qty')->nullable();
            $table->string('outcome', 12)->nullable()->comment('accepted | rejected | partial | restocked | written_off');
            $table->string('created_by_name')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['supplier_return_id', 'seq']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
    }
};
