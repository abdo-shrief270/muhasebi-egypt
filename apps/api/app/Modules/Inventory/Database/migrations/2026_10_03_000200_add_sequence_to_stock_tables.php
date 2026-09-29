<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Several movements (and lots) are written in the same second, and UUIDv7 ids are only
     * ordered to the millisecond: an always-increasing sequence gives the ledger and FIFO a
     * reliable order.
     */
    public function up(): void
    {
        foreach (['stock_movements', 'stock_lots'] as $table) {
            DB::statement("alter table {$table} add column seq bigint generated always as identity");
        }
        DB::statement('alter table stock_movements alter column created_at type timestamp(6)');
        DB::statement('alter table stock_lots alter column received_at type timestamp(6)');
        DB::statement('create index stock_movements_branch_variant_seq on stock_movements (branch_id, variant_id, seq)');
        DB::statement('create index stock_lots_fifo on stock_lots (branch_id, variant_id, seq) where qty_remaining > 0');
    }

    public function down(): void
    {
        DB::statement('drop index if exists stock_lots_fifo');
        DB::statement('drop index if exists stock_movements_branch_variant_seq');
        foreach (['stock_movements', 'stock_lots'] as $table) {
            DB::statement("alter table {$table} drop column seq");
        }
    }
};
