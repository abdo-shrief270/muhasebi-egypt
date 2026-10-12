<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer's rating of the shop, once per delivered order (a real order, not anyone).
        Schema::create('online_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->unique()->constrained('online_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 500)->nullable();
            $table->string('customer_name', 60);
            $table->boolean('hidden')->default(false)->comment('by the platform admins');
            $table->string('source', 10)->default('store');
            $table->timestamps();
            $table->index(['tenant_id', 'hidden', 'created_at']);
        });

        // How fast the shop answers: when the order first left «جديد», and orders the platform
        // cancelled because the shop never answered.
        Schema::table('online_orders', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable();
            $table->boolean('auto_cancelled')->default(false);
        });
        DB::statement("update online_orders o set answered_at = (select min(e.created_at) from online_order_events e where e.order_id = o.id and e.status <> 'new') where status <> 'new'");

        Schema::table('online_repair_bookings', function (Blueprint $table) {
            $table->string('source', 10)->default('store');
        });
    }

    public function down(): void
    {
        Schema::table('online_repair_bookings', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('online_orders', fn (Blueprint $table) => $table->dropColumn(['answered_at', 'auto_cancelled']));
        Schema::dropIfExists('online_reviews');
    }
};
