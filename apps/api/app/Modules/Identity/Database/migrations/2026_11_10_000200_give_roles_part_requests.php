<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Part requests are built: technicians ask, the store / counter / managers hand out — as new shops' roles do.
    public function up(): void
    {
        $add = [
            'technician' => ['repairs.request_parts'],
            'storekeeper' => ['repairs.hand_parts'],
            'cashier' => ['repairs.hand_parts'],
            'manager' => ['repairs.request_parts', 'repairs.hand_parts'],
        ];
        foreach (DB::table('roles')->whereIn('key', array_keys($add))->get(['id', 'key', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $missing = array_values(array_diff($add[$role->key], $permissions));
            if ($missing !== []) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, ...$missing])]);
            }
        }
    }

    public function down(): void {}
};
