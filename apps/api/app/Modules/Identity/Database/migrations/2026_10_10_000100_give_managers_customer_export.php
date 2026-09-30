<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // New permission: exporting a customer's personal data is for owners and managers only.
    public function up(): void
    {
        foreach (DB::table('roles')->where('key', 'manager')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if (! in_array('customers.export', $permissions, true)) {
                $permissions[] = 'customers.export';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    public function down(): void {}
};
