<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What broke on the server (uncaught exceptions), one row per kind of error with a count,
        // for the platform admins. Not shop data: the shop it happened in is only a hint.
        Schema::create('server_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('fingerprint', 40)->unique()->comment('sha1 of the class + file + line');
            $table->string('class', 255);
            $table->string('message', 500);
            $table->string('file', 255)->nullable();
            $table->text('trace')->nullable();
            $table->string('context', 255)->nullable()->comment('method + path, or the console command / job');
            $table->uuid('last_tenant_id')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable()->comment('a new occurrence reopens it');

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_errors');
    }
};
