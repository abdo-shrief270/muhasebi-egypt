<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\User;
use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes shops for good (test shops on a live server). Every table holding a shop's rows is found
 * from the schema itself — a foreign key to `tenants` or a `tenant_id` column — so a module added
 * later is covered without touching this. Tables are emptied children first, so restricting foreign
 * keys hold, then the shops themselves; their users' sign-in tokens and sessions go too. After the
 * commit: every `…/{shop id}` folder on the local disk (product photos, proofs, used-device photos…)
 * and the shops' marketplace offers. The platform admins' own log keeps its rows.
 */
final class ShopEraser
{
    /** The admins' log is the record that the shop existed and who deleted it. */
    private const KEEP = ['platform_admin_actions'];

    /** How deep shops' folders sit on the local disk (`market/used/{id}` is the deepest). */
    private const FOLDER_DEPTH = 3;

    public function __construct(private readonly SearchClient $search) {}

    /**
     * What erase() would remove; nothing changes.
     *
     * @param  list<string>  $tenantIds
     * @return array{tables: array<string, int>, tokens: int, files: int}
     */
    public function plan(array $tenantIds): array
    {
        $tables = [];
        foreach ($this->tables() as $table => $columns) {
            $count = $this->rows($table, $columns, $tenantIds)->count();
            if ($count > 0) {
                $tables[$table] = $count;
            }
        }

        return [
            'tables' => $tables,
            'tokens' => $this->tokens($tenantIds)->count(),
            'files' => array_sum(array_map(fn (string $folder) => count($this->disk()->allFiles($folder)), $this->folders($tenantIds))),
        ];
    }

    /**
     * Deletes the shops. Call it inside the caller's transaction if it has one: the files and the
     * marketplace offers go only once that commits.
     *
     * @param  list<string>  $tenantIds
     * @return array{tables: array<string, int>, tokens: int, files: int} what was removed
     */
    public function erase(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return ['tables' => [], 'tokens' => 0, 'files' => 0];
        }
        $removed = $this->plan($tenantIds);
        $folders = $this->folders($tenantIds);

        DB::transaction(function () use ($tenantIds): void {
            $users = User::query()->whereIn('tenant_id', $tenantIds)->select('id');
            $this->tokens($tenantIds)->delete();
            DB::table('sessions')->whereIn('user_id', $users)->delete();
            foreach ($this->tables() as $table => $columns) {
                $this->rows($table, $columns, $tenantIds)->delete();
            }
            DB::table('tenants')->whereIn('id', $tenantIds)->delete();
        });

        DB::afterCommit(function () use ($folders, $tenantIds): void {
            foreach ($folders as $folder) {
                $this->disk()->deleteDirectory($folder);
            }
            $this->forgetOffers($tenantIds);
        });

        return $removed;
    }

    /**
     * Every table holding shops' rows → its columns pointing at the shop, children before parents.
     *
     * @return array<string, list<string>>
     */
    private function tables(): array
    {
        $columns = [];
        $found = DB::select(<<<'SQL'
            select c.conrelid::regclass::text as "table", a.attname as "column"
            from pg_constraint c
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f' and c.confrelid = 'tenants'::regclass and cardinality(c.conkey) = 1
            union
            select c.table_name, c.column_name from information_schema.columns c
            join information_schema.tables t on t.table_schema = c.table_schema and t.table_name = c.table_name
            where c.table_schema = current_schema() and c.column_name = 'tenant_id' and t.table_type = 'BASE TABLE'
            SQL);
        foreach ($found as $row) {
            if (! in_array($row->table, self::KEEP, true)) {
                $columns[$row->table][] = $row->column;
            }
        }

        // A table waits until every table referencing it is emptied. A cycle (none today) leaves
        // the rest in any order: the delete then fails and everything rolls back.
        $waits = array_fill_keys(array_keys($columns), []);
        $references = DB::select(<<<'SQL'
            select distinct c.conrelid::regclass::text as child, c.confrelid::regclass::text as parent
            from pg_constraint c
            where c.contype = 'f' and c.conrelid <> c.confrelid
            SQL);
        foreach ($references as $fk) {
            if (isset($columns[$fk->child], $columns[$fk->parent])) {
                $waits[$fk->parent][$fk->child] = true;
            }
        }
        $ordered = [];
        while ($waits !== []) {
            $ready = array_keys(array_filter($waits, fn (array $children) => $children === [])) ?: array_keys($waits);
            foreach ($ready as $table) {
                $ordered[$table] = $columns[$table];
                unset($waits[$table]);
                foreach (array_keys($waits) as $parent) {
                    unset($waits[$parent][$table]);
                }
            }
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $tenantIds
     */
    private function rows(string $table, array $columns, array $tenantIds): Builder
    {
        return DB::table($table)->where(function (Builder $q) use ($columns, $tenantIds): void {
            foreach ($columns as $column) {
                $q->orWhereIn($column, $tenantIds);
            }
        });
    }

    /** @param  list<string>  $tenantIds */
    private function tokens(array $tenantIds): Builder
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::query()->whereIn('tenant_id', $tenantIds)->select('id'));
    }

    /**
     * Every folder named after one of the shops on the local disk (`products/{id}`, `market/used/{id}`…).
     *
     * @param  list<string>  $tenantIds
     * @return list<string>
     */
    private function folders(array $tenantIds): array
    {
        $ids = array_flip($tenantIds);
        $found = [];
        $walk = function (string $folder, int $depth) use (&$walk, &$found, $ids): void {
            foreach ($this->disk()->directories($folder) as $sub) {
                if (isset($ids[basename($sub)])) {
                    $found[] = $sub;
                } elseif ($depth < self::FOLDER_DEPTH) {
                    $walk($sub, $depth + 1);
                }
            }
        };
        $walk('', 1);

        return $found;
    }

    /** @param  list<string>  $tenantIds */
    private function forgetOffers(array $tenantIds): void
    {
        if (! $this->search->enabled()) {
            return;
        }
        try {
            $this->search->deleteByQuery($this->search->name(MarketIndices::OFFERS), ['terms' => ['tenant_id' => $tenantIds]], refresh: true);
        } catch (Throwable $e) {
            // The nightly rebuild leaves them out anyway.
            report($e);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
