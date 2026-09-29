<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How each technician is paid per repaired device.
        Schema::create('repair_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('type', 10)->comment('percent | fixed');
            $table->unsignedBigInteger('value')->comment('percent: basis points (1500 = 15%); fixed: piasters per device');
            $table->string('base', 10)->default('labor')->comment('percent of: labor | profit');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::table('repair_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('commission')->default(0)->after('credit')->comment("the technician's, fixed at delivery");
            $table->string('commission_rule', 60)->nullable()->after('commission');
        });
    }

    public function down(): void
    {
        Schema::table('repair_tickets', fn (Blueprint $table) => $table->dropColumn(['commission', 'commission_rule']));
        Schema::dropIfExists('repair_commission_rules');
    }
};
