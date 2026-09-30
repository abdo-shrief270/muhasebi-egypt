<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A free beta period granted by a platform admin (a zero invoice with method «beta»):
        // the shop is active, but it isn't revenue.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('beta_until')->nullable()->after('paid_until');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('beta_until');
        });
    }
};
