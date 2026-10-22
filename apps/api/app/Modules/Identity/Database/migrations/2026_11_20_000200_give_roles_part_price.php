<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Changing a part's price on the ticket: technicians and managers, as new shops' roles have it.
    public function up(): void
    {
        foreach (DB::table('roles')->whereIn('key', ['technician', 'manager'])->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if (! in_array('repairs.part_price', $permissions, true)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, 'repairs.part_price'])]);
            }
        }
    }

    public function down(): void {}
};
