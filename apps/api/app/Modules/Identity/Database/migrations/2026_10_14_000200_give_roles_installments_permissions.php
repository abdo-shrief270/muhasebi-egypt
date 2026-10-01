<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The Installments module is built: managers make and collect plans, cashiers collect — as in new shops.
    public function up(): void
    {
        $add = ['manager' => ['installments.manage', 'installments.collect'], 'cashier' => ['installments.collect']];
        foreach (DB::table('roles')->whereIn('key', array_keys($add))->get(['id', 'key', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $missing = array_diff($add[$role->key], $permissions);
            if ($missing !== []) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values([...$permissions, ...$missing]))]);
            }
        }
    }

    public function down(): void {}
};
