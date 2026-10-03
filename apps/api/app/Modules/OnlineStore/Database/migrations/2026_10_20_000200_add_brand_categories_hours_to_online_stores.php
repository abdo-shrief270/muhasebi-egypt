<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_stores', function (Blueprint $table) {
            $table->boolean('show_brand')->default(true);
            // What a category is called on the store, when not its name in the app ({category id: name}).
            $table->jsonb('category_names')->nullable();
            // When orders are taken (Cairo time); both null = any time. Until < from = past midnight.
            $table->time('orders_from')->nullable();
            $table->time('orders_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table) {
            $table->dropColumn(['show_brand', 'category_names', 'orders_from', 'orders_until']);
        });
    }
};
