<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The starting list of phones (database/data/market/phones.json) into `market_phones`. */
final class PhoneList
{
    /** Adds the phones that aren't there yet (by slug); never touches the ones already in the table. */
    public static function import(?string $path = null): int
    {
        $path ??= database_path('data/market/phones.json');
        if (! is_file($path)) {
            return 0;
        }

        $phones = json_decode((string) file_get_contents($path), true)['phones'] ?? [];
        $now = now();
        $rows = [];
        foreach ($phones as $phone) {
            $slug = self::slug($phone['brand'], $phone['model']);
            $rows[$slug] ??= [
                'id' => (string) Str::uuid7(),
                'brand' => $phone['brand'],
                'series' => $phone['series'] ?? null,
                'model' => $phone['model'],
                'slug' => $slug,
                'aliases' => json_encode(array_values($phone['aliases'] ?? []), JSON_UNESCAPED_UNICODE),
                'year' => $phone['year'] ?? null,
                'storage_gb' => json_encode(array_values($phone['storage_gb'] ?? [])),
                'network' => $phone['network'] ?? null,
                'status' => ($phone['verify'] ?? false) ? 'review' : 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $added = 0;
        foreach (array_chunk(array_values($rows), 200) as $chunk) {
            $added += DB::table('market_phones')->insertOrIgnore($chunk);
        }

        return $added;
    }

    public static function slug(string $brand, string $model): string
    {
        return Str::slug($brand.' '.$model);
    }
}
