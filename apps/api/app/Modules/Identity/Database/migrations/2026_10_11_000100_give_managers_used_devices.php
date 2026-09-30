<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The used devices module is ready: branch managers buy used devices and, like the owner, see who sold them (ID card).
    public function up(): void
    {
        foreach (DB::table('roles')->where('key', 'manager')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $missing = array_diff(['used_devices.manage', 'used_devices.view_seller'], $permissions);
            if ($missing !== []) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values([...$permissions, ...$missing]))]);
            }
        }
    }

    public function down(): void {}
};
