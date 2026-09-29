<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything a platform admin does (and every sign-in attempt). Append-only.
        Schema::create('platform_admin_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->string('action', 40);
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at', 6);
            $table->bigInteger('seq')->generatedAs()->always();

            $table->index('seq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admin_actions');
    }
};
