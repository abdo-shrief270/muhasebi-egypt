<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cashier asking the owner / a manager to OK something past the owner's limits.
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->string('kind', 20);
            $table->string('summary', 300);
            $table->bigInteger('amount')->default(0)->comment('piasters, for display');
            $table->string('payload_hash', 64)->comment('sha256 of exactly the action it allows');
            $table->uuid('requested_by');
            $table->string('requested_by_name');
            $table->string('status', 10)->default('pending')->comment('pending | approved | denied | expired');
            $table->string('via', 10)->nullable()->comment('app | pin');
            $table->uuid('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('reason', 300)->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['requested_by', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
