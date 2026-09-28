<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32)->nullable()->comment('set for the default roles every shop starts with');
            $table->string('name', 80);
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('is_owner')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
        });

        // Which branches a (non-owner) user may work in.
        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('roles');
    }
};
