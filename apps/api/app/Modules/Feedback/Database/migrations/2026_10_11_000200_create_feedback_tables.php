<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «ابعت ملاحظة»: what a user tells the platform team, with where they were in the app.
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('type', 12)->comment('problem | suggestion | question');
            $table->text('message');
            $table->string('page', 255)->nullable()->comment('the web path, no query string');
            $table->string('app_version', 60)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('screen', 20)->nullable()->comment('e.g. 390x844');
            $table->string('screenshot_path')->nullable()->comment('local (private) disk');
            $table->string('status', 8)->default('new')->comment('new | seen | done');
            $table->timestamp('status_changed_at')->nullable();
            $table->string('status_changed_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Uncaught errors in the shop app, one row per shop and error (fingerprint), counted.
        Schema::create('client_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 40)->comment('sha1 of the normalised message + source');
            $table->string('kind', 12)->comment('error | rejection');
            $table->string('message', 500);
            $table->string('source', 255)->nullable()->comment('file:line:column');
            $table->text('stack')->nullable();
            $table->string('page', 255)->nullable();
            $table->string('app_version', 60)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->uuid('last_user_id')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable()->comment('a platform admin marked it fixed; a new occurrence reopens it');

            $table->unique(['tenant_id', 'fingerprint']);
            $table->index(['fingerprint', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_errors');
        Schema::dropIfExists('feedback');
    }
};
