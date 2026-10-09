<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Where the branch is, for the marketplace («الأقرب ليك», the governorate filter).
            $table->string('governorate', 20)->nullable()->after('address');
            $table->string('area', 80)->nullable()->after('governorate');
            $table->decimal('latitude', 9, 6)->nullable()->after('area');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['governorate', 'area', 'latitude', 'longitude']));
    }
};
