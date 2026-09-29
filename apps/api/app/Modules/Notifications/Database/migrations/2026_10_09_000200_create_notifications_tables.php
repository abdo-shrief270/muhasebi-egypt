<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per shop; every user who has `permission` (or everyone when null) sees it.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('title');
            $table->string('body', 500)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('to', 255)->nullable()->comment('web route to open');
            $table->string('permission', 60)->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index(['tenant_id', 'seq']);
        });

        // Who read which.
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->foreignUuid('notification_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->primary(['notification_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('notifications');
    }
};
