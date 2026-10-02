<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Approvals are built: branch managers may approve cashiers' requests, as new shops' managers do.
    public function up(): void
    {
        foreach (DB::table('roles')->where('key', 'manager')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if (! in_array('owner_app.approve', $permissions, true)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, 'owner_app.approve'])]);
            }
        }
    }

    public function down(): void {}
};
