<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer asked about something (an item not in stock, a repair price, a phone): who,
        // what, when to call them back, and how it ended.
        Schema::create('inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20)->nullable();
            $table->string('kind', 10)->comment('item | repair | phone | other');
            $table->string('text', 1000);
            $table->string('status', 10)->default('open')->comment('open | answered | converted | closed');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->string('outcome', 300)->nullable();
            $table->string('converted_to', 10)->nullable()->comment('sale | repair');
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name', 120)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['status', 'follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
