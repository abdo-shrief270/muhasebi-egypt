<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->string('user_name', 120);
            $table->string('action', 64);
            $table->string('description', 500);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
