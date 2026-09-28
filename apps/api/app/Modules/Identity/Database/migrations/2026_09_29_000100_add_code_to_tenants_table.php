<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('code', 12)->nullable()->unique()->after('name');
        });

        // Backfill existing shops, then make it required.
        foreach (DB::table('tenants')->whereNull('code')->pluck('id') as $id) {
            DB::table('tenants')->where('id', $id)->update(['code' => strtoupper(substr(str_replace('-', '', (string) $id), -6))]);
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('code', 12)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
