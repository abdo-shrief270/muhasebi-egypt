<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transactional outbox: written in the same transaction as the change it describes.
        Schema::create('domain_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('name');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('event_class');
            $table->json('payload');
            $table->timestamp('occurred_at', 6);
            $table->timestamp('published_at', 6)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->index(['published_at', 'occurred_at']);
            $table->index(['name', 'occurred_at']);
        });

        // Idempotency ledger: one row per (event, listener) that already ran.
        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->string('listener');
            $table->timestamp('processed_at');

            $table->primary(['event_id', 'listener']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('domain_events');
    }
};
