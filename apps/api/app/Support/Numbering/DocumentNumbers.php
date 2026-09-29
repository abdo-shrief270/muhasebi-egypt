<?php

declare(strict_types=1);

namespace App\Support\Numbering;

use Illuminate\Support\Facades\DB;

/**
 * Gapless per-shop numbers (purchase #1, #2…). Call inside the transaction that creates the
 * document: the counter row stays locked until it commits, and a rollback gives the number back.
 */
final class DocumentNumbers
{
    public function next(string $tenantId, string $key): int
    {
        return (int) DB::selectOne(
            'insert into document_counters (tenant_id, key, last_number) values (?, ?, 1)
             on conflict (tenant_id, key) do update set last_number = document_counters.last_number + 1
             returning last_number',
            [$tenantId, $key],
        )->last_number;
    }
}
