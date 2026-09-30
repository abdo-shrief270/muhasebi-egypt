<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stamped by DeviceSessions::issue() on every sign-in (devices are deleted on logout).
            $table->timestamp('last_login_at')->nullable();
        });

        // Best guess for existing users: their newest signed-in device.
        DB::statement(<<<'SQL'
            update users set last_login_at = t.signed_in
            from (select tokenable_id, max(created_at) as signed_in from personal_access_tokens group by tokenable_id) t
            where t.tokenable_id = users.id
        SQL);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
