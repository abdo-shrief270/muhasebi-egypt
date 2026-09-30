<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The Services module (wallets / airtime) is built: branch managers run all of it, as new shops' managers do.
    public function up(): void
    {
        $add = ['services.manage', 'services.fees', 'services.fund', 'services.settings'];
        foreach (DB::table('roles')->where('key', 'manager')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $missing = array_diff($add, $permissions);
            if ($missing !== []) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values([...$permissions, ...$missing]))]);
            }
        }
    }

    public function down(): void {}
};
