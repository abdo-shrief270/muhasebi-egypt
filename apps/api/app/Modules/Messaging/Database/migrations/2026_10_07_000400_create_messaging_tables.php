<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shop's own wording of a message; the built-in text is used when there is no row.
        Schema::create('message_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->text('body');
            $table->string('updated_by_name')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        // WhatsApp opened with a message for someone (we can't see whether it was really sent). Append-only.
        Schema::create('message_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('template', 40);
            $table->string('subject_type', 30)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('phone', 20)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['subject_type', 'subject_id', 'seq']);
            $table->index(['tenant_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('message_templates');
    }
};
