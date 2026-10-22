<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use Illuminate\Support\Facades\DB;

/** The shop's warranty wording and the labor's default days (one `repair_settings` row, none = defaults). */
final class WarrantySettings
{
    public const DEFAULT_TERMS = 'الضمان على عيوب التركيب والقطع اللي اتغيّرت بس، ومش بيشمل الكسر أو المية أو فتح الجهاز برّه المحل. لازم إيصال التسليم.';

    /** @return array{warranty_terms: string, labor_warranty_days: int} */
    public static function get(string $tenantId): array
    {
        $row = DB::table('repair_settings')->where('tenant_id', $tenantId)->first();

        return [
            'warranty_terms' => $row->warranty_terms ?? self::DEFAULT_TERMS,
            'labor_warranty_days' => (int) ($row->labor_warranty_days ?? 30),
        ];
    }

    /** @param  array{warranty_terms?: string|null, labor_warranty_days?: int}  $data */
    public static function save(string $tenantId, array $data): void
    {
        DB::table('repair_settings')->updateOrInsert(['tenant_id' => $tenantId], [...$data, 'updated_at' => now(), 'created_at' => now()]);
    }
}
