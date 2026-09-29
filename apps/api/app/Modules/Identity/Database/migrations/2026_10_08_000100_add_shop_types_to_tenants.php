<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shop may be several types at once (accessories + repair + phones…); shop_type keeps the first.
        Schema::table('tenants', fn (Blueprint $table) => $table->jsonb('shop_types')->nullable());

        DB::table('tenants')->where('shop_type', 'accessories_repair')->update(['shop_types' => json_encode(['accessories', 'repair'])]);
        DB::statement('update tenants set shop_types = jsonb_build_array(shop_type) where shop_types is null');
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('shop_types'));
    }
};
