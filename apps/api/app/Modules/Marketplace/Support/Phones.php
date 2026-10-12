<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Phones implements PhoneCatalog
{
    public function browse(array $filters = []): array
    {
        $words = preg_split('/\s+/u', trim((string) ($filters['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $search = function (Builder $query) use ($words): void {
            foreach ($words as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                $query->whereRaw("(brand || ' ' || coalesce(series, '') || ' ' || model || ' ' || aliases::text) ilike ?", [$like]);
            }
        };

        $brand = $filters['brand'] ?? null;
        $status = $filters['status'] ?? null;

        $phones = DB::table('market_phones')
            ->tap($search)
            ->when($brand, fn (Builder $q) => $q->where('brand', $brand))
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->orderBy('brand')->orderByDesc('year')->orderBy('model')
            ->get()->map($this->present(...))->all();

        // Counts follow the search, each ignoring its own filter, so the chips say what a click gives.
        $brands = DB::table('market_phones')->tap($search)
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->selectRaw('brand, count(*) as n')->groupBy('brand')->orderByDesc('n')->orderBy('brand')
            ->pluck('n', 'brand')->map(fn ($n) => (int) $n)->all();
        $statuses = DB::table('market_phones')->tap($search)
            ->when($brand, fn (Builder $q) => $q->where('brand', $brand))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();

        return [
            'phones' => $phones,
            'brands' => $brands,
            'statuses' => array_map(fn (string $s) => $statuses[$s] ?? 0, array_combine(self::STATUSES, self::STATUSES)),
            'total' => DB::table('market_phones')->count(),
        ];
    }

    public function find(string $id): ?array
    {
        $row = DB::table('market_phones')->find($id);

        return $row ? $this->present($row) : null;
    }

    public function save(?string $id, array $data, ?string $by = null): array
    {
        $current = $id !== null ? DB::table('market_phones')->find($id) : null;
        if ($id !== null && $current === null) {
            throw new DomainRuleException('الموبايل ده مش موجود.', 'phone_not_found', 404);
        }

        $brand = trim((string) ($data['brand'] ?? $current?->brand));
        $model = trim((string) ($data['model'] ?? $current?->model));
        $slug = PhoneList::slug($brand, $model) ?: 'phone-'.substr(md5($brand.' '.$model), 0, 10);

        $taken = DB::table('market_phones')->where('slug', $slug)->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists();
        if ($taken) {
            throw new DomainRuleException("«{$brand} {$model}» موجود في القايمة بالفعل.", 'phone_exists');
        }

        $row = [
            'brand' => $brand,
            'model' => $model,
            'slug' => $slug,
            'updated_by_name' => $by,
            'updated_at' => now(),
        ];
        foreach (['series', 'year', 'network', 'status', 'notes'] as $key) {
            if (array_key_exists($key, $data)) {
                $row[$key] = is_string($data[$key]) ? (trim($data[$key]) ?: null) : $data[$key];
            }
        }
        if (array_key_exists('aliases', $data)) {
            $aliases = array_values(array_unique(array_filter(array_map(fn ($a) => trim((string) $a), $data['aliases'] ?? []))));
            $row['aliases'] = json_encode($aliases, JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('storage_gb', $data)) {
            $storage = array_values(array_unique(array_map('intval', $data['storage_gb'] ?? [])));
            sort($storage);
            $row['storage_gb'] = json_encode($storage);
        }

        if ($current === null) {
            $id = (string) Str::uuid7();
            DB::table('market_phones')->insert([...$row, 'id' => $id, 'status' => $row['status'] ?? 'published', 'created_at' => now()]);
        } else {
            DB::table('market_phones')->where('id', $id)->update($row);
        }

        PhoneMatcher::forget();

        return $this->find($id) ?? [];
    }

    public function delete(string $id): bool
    {
        $deleted = DB::table('market_phones')->where('id', $id)->delete() > 0;
        PhoneMatcher::forget();

        return $deleted;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = DB::table('market_phones')->where('slug', $slug)->where('status', 'published')->first();

        return $row ? $this->present($row) : null;
    }

    public function many(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('market_phones')->whereIn('id', array_values(array_unique($ids)))->get()
            ->mapWithKeys(fn (object $row) => [$row->id => $this->present($row)])->all();
    }

    /** «OPPO A58», not «OPPO OPPO A58»: most makers write their name into the model. */
    public static function name(string $brand, string $model): string
    {
        return str_starts_with(mb_strtolower($model), mb_strtolower($brand)) ? $model : "{$brand} {$model}";
    }

    public function toReview(): int
    {
        return DB::table('market_phones')->where('status', 'review')->count();
    }

    public function published(): array
    {
        return DB::table('market_phones')->where('status', 'published')
            ->orderBy('brand')->orderByDesc('year')->orderBy('model')
            ->get()->map($this->present(...))->all();
    }

    /** @return array<string, mixed> */
    private function present(object $row): array
    {
        return [
            'id' => $row->id,
            'brand' => $row->brand,
            'series' => $row->series,
            'model' => $row->model,
            'name' => self::name($row->brand, $row->model),
            'slug' => $row->slug,
            'aliases' => json_decode((string) $row->aliases, true) ?: [],
            'year' => $row->year !== null ? (int) $row->year : null,
            'storage_gb' => json_decode((string) $row->storage_gb, true) ?: [],
            'network' => $row->network,
            'status' => $row->status,
            'notes' => $row->notes,
            'updated_by_name' => $row->updated_by_name,
            'updated_at' => $row->updated_at,
        ];
    }
}
