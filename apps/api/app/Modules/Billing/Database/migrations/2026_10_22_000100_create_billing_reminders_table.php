<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each renewal reminder goes once per subscription end date and step (7 days before, the day, …).
        Schema::create('billing_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('ends_on');
            $table->string('step', 16);
            $table->timestamp('sent_at');

            $table->unique(['tenant_id', 'ends_on', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reminders');
    }
};
