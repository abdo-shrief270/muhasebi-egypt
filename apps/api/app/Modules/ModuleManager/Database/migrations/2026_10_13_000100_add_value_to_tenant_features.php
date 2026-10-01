<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The value set next to a switch (e.g. the return window in days); null = the setting's default.
        Schema::table('tenant_features', function (Blueprint $table) {
            $table->string('value', 60)->nullable()->after('enabled');
        });

        // «الخصم في الكاشير» used to cover line discounts and price levels too; they are their own
        // switches now. A shop that had it off keeps all three off.
        $off = DB::table('tenant_features')->where('feature_key', 'sales.discounts')->where('enabled', false)->get();
        foreach ($off as $row) {
            foreach (['sales.line_discounts', 'sales.price_levels'] as $key) {
                DB::table('tenant_features')->insertOrIgnore([
                    'tenant_id' => $row->tenant_id,
                    'feature_key' => $key,
                    'enabled' => false,
                    'updated_by_name' => $row->updated_by_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tenant_features', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
