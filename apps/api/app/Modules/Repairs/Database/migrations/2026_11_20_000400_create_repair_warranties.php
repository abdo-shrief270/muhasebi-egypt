<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // What a delivered repair is guaranteed for, item by item: each part fitted and the labor,
        // each with its own days. The ticket's warranty_until is the latest still standing.
        Schema::create('repair_warranties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('repair_tickets')->cascadeOnDelete();
            $table->uuid('branch_id');
            $table->uuid('customer_id');
            $table->uuid('device_id')->nullable();
            $table->unsignedBigInteger('part_id')->nullable();
            $table->string('kind', 10)->comment('part | labor | repair (the whole repair, older tickets)');
            $table->string('name', 160);
            $table->unsignedSmallInteger('days');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->string('voided_by_name', 120)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'ends_at']);
            $table->index(['tenant_id', 'ticket_id']);
        });

        Schema::table('repair_ticket_parts', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_days')->nullable()->comment("the product's default when it was fitted");
        });

        // The shop's warranty wording (printed on the card) and the labor's default days.
        Schema::create('repair_settings', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('warranty_terms')->nullable();
            $table->unsignedSmallInteger('labor_warranty_days')->default(30);
            $table->timestamps();
        });

        // Tickets delivered with a warranty so far: one line for the whole repair.
        DB::table('repair_tickets')->where('warranty_days', '>', 0)->whereNotNull('warranty_until')->whereNotNull('delivered_at')
            ->orderBy('id')->chunk(500, function ($tickets): void {
                DB::table('repair_warranties')->insert($tickets->map(fn ($t) => [
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $t->tenant_id,
                    'ticket_id' => $t->id,
                    'branch_id' => $t->branch_id,
                    'customer_id' => $t->customer_id,
                    'device_id' => $t->device_id,
                    'kind' => 'repair',
                    'name' => 'الصيانة كلها',
                    'days' => $t->warranty_days,
                    'starts_at' => $t->delivered_at,
                    'ends_at' => $t->warranty_until,
                    'created_at' => $t->delivered_at,
                    'updated_at' => $t->delivered_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_settings');
        Schema::table('repair_ticket_parts', fn (Blueprint $table) => $table->dropColumn('warranty_days'));
        Schema::dropIfExists('repair_warranties');
    }
};
