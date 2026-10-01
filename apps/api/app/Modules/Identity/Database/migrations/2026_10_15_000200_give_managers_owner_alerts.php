<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The owner app's alerts are built: branch managers get them, as new shops' managers do.
    public function up(): void
    {
        foreach (DB::table('roles')->where('key', 'manager')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if (! in_array('owner_app.alerts', $permissions, true)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, 'owner_app.alerts'])]);
            }
        }
    }

    public function down(): void {}
};
