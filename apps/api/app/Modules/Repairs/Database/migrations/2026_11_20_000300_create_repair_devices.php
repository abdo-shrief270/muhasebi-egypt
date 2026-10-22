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
        // A customer's device, as the shop knows it: every ticket for it hangs off it, so its history
        // (faults, technicians, prices, warranty) shows on the customer and at the next intake.
        Schema::create('repair_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('device_model_id')->nullable();
            $table->string('name', 160);
            $table->string('imei', 40)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'imei']);
        });

        Schema::table('repair_tickets', function (Blueprint $table) {
            $table->foreignUuid('device_id')->nullable()->after('customer_phone')->constrained('repair_devices')->nullOnDelete();
        });

        // Tickets so far: one device per customer + IMEI, else per customer + model (or name) + colour.
        $devices = [];
        DB::table('repair_tickets')->orderBy('received_at')->orderBy('id')
            ->select(['id', 'tenant_id', 'customer_id', 'device_model_id', 'device_name', 'imei', 'color', 'received_at'])
            ->chunk(500, function ($tickets) use (&$devices): void {
                foreach ($tickets as $t) {
                    $key = $t->tenant_id.'|'.$t->customer_id.'|'.(
                        $t->imei ? 'imei:'.$t->imei : 'model:'.($t->device_model_id ?? mb_strtolower(trim($t->device_name))).'|'.mb_strtolower(trim((string) $t->color))
                    );
                    if (! isset($devices[$key])) {
                        $devices[$key] = (string) Str::uuid7();
                        DB::table('repair_devices')->insert([
                            'id' => $devices[$key],
                            'tenant_id' => $t->tenant_id,
                            'customer_id' => $t->customer_id,
                            'device_model_id' => $t->device_model_id,
                            'name' => $t->device_name,
                            'imei' => $t->imei,
                            'color' => $t->color,
                            'created_at' => $t->received_at,
                            'updated_at' => $t->received_at,
                        ]);
                    }
                    DB::table('repair_devices')->where('id', $devices[$key])->update(['last_seen_at' => $t->received_at]);
                    DB::table('repair_tickets')->where('id', $t->id)->update(['device_id' => $devices[$key]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('repair_tickets', fn (Blueprint $table) => $table->dropConstrainedForeignId('device_id'));
        Schema::dropIfExists('repair_devices');
    }
};
