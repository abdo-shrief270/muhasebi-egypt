<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shop's own domain for its store (www.elnour-mobile.com). Served only once the TXT
        // record (_muhasebi.<domain> = muhasebi-verify=<token>) proved it's theirs.
        Schema::table('online_stores', function (Blueprint $table) {
            $table->string('custom_domain', 253)->nullable()->unique();
            $table->string('custom_domain_token', 40)->nullable();
            $table->timestamp('custom_domain_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', fn (Blueprint $table) => $table->dropColumn(['custom_domain', 'custom_domain_token', 'custom_domain_verified_at']));
    }
};
