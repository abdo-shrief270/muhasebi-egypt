<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A browser / installed app that receives push notifications for a user (Web Push).
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->string('endpoint', 1000)->unique();
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('device', 120)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // What each user wants pushed to their devices (the bell always shows everything they may see).
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('muted')->comment('categories not pushed');
            $table->time('quiet_from')->nullable()->comment('no pushes from…');
            $table->time('quiet_to')->nullable()->comment('…until (Cairo time); urgent ones still come');
            $table->timestamps();
        });

        // Which shop already got which day's summary (so it's sent once).
        Schema::create('daily_summaries_sent', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('day');

            $table->primary(['tenant_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries_sent');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('push_subscriptions');
    }
};
