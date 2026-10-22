<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // «الاستعلامات»: the counter, technicians and managers record and follow them, as new shops' roles do.
    public function up(): void
    {
        foreach (DB::table('roles')->whereIn('key', ['cashier', 'technician', 'manager'])->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $missing = array_values(array_diff(['inquiries.view', 'inquiries.manage'], $permissions));
            if ($missing !== []) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, ...$missing])]);
            }
        }
    }

    public function down(): void {}
};
