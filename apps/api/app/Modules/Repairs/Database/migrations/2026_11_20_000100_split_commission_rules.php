<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A technician's share adds up from parts: a percent of the labor, a percent of the parts
        // (their price or their profit), a percent of the whole repair's profit, and a fixed amount.
        Schema::table('repair_commission_rules', function (Blueprint $table) {
            $table->unsignedInteger('labor_rate')->default(0)->comment('basis points of labor after the discount');
            $table->unsignedInteger('parts_rate')->default(0)->comment('basis points of the parts, by parts_base');
            $table->string('parts_base', 10)->default('price')->comment('price | profit');
            $table->unsignedInteger('profit_rate')->default(0)->comment('basis points of the bill less what the repair cost');
            $table->unsignedBigInteger('fixed')->default(0)->comment('piasters per device');
            $table->boolean('warranty_clawback')->default(false)->comment('a warranty return takes the first ticket\'s commission back');
        });
        foreach (DB::table('repair_commission_rules')->get() as $rule) {
            DB::table('repair_commission_rules')->where('id', $rule->id)->update(match (true) {
                $rule->type === 'fixed' => ['fixed' => $rule->value],
                $rule->base === 'profit' => ['profit_rate' => $rule->value],
                default => ['labor_rate' => $rule->value],
            });
        }
        Schema::table('repair_commission_rules', fn (Blueprint $table) => $table->dropColumn(['type', 'value', 'base']));
        Schema::table('repair_tickets', fn (Blueprint $table) => $table->string('commission_rule', 160)->nullable()->change());

        // A part's price changed on the ticket (for this customer): the shop's price it replaced and why.
        Schema::table('repair_ticket_parts', function (Blueprint $table) {
            $table->unsignedBigInteger('list_price')->nullable()->comment('the price before someone changed it on the ticket');
            $table->string('price_note', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repair_ticket_parts', fn (Blueprint $table) => $table->dropColumn(['list_price', 'price_note']));
        Schema::table('repair_commission_rules', function (Blueprint $table) {
            $table->string('type', 10)->default('percent');
            $table->unsignedBigInteger('value')->default(0);
            $table->string('base', 10)->default('labor');
            $table->dropColumn(['labor_rate', 'parts_rate', 'parts_base', 'profit_rate', 'fixed', 'warranty_clawback']);
        });
    }
};
