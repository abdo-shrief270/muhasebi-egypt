<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\PlatformShops;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Support\Text\SearchText;

final class PlatformShopsService implements PlatformShops
{
    public function searchIds(string $q, int $limit = 200): array
    {
        $like = SearchText::like(mb_strtolower(trim($q)));
        $digits = preg_replace('/\D/', '', $q);

        return Tenant::query()
            ->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(code) like ?', [$like])
                ->when(strlen((string) $digits) >= 4, fn ($d) => $d->orWhere('phone', 'like', '%'.$digits.'%')
                    ->orWhereIn('id', User::query()->where('is_owner', true)->where('phone', 'like', '%'.$digits.'%')->select('tenant_id'))))
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    public function details(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }
        $owners = User::query()->whereIn('tenant_id', $tenantIds)->where('is_owner', true)->get(['tenant_id', 'name', 'phone'])->keyBy('tenant_id');
        $users = User::query()->whereIn('tenant_id', $tenantIds)->selectRaw('tenant_id, count(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');
        $branches = Branch::withoutTenancy()->whereIn('tenant_id', $tenantIds)->selectRaw('tenant_id, count(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');

        return Tenant::query()->whereIn('id', $tenantIds)->get()->mapWithKeys(fn (Tenant $t): array => [$t->id => [
            'id' => $t->id,
            'name' => $t->name,
            'code' => $t->code,
            'phone' => $t->phone,
            'types' => ShopType::labels($t->types()),
            'owner_name' => $owners->get($t->id)?->name,
            'owner_phone' => $owners->get($t->id)?->phone,
            'users' => (int) ($users[$t->id] ?? 0),
            'branches' => (int) ($branches[$t->id] ?? 0),
            'created_at' => $t->created_at?->toIso8601String() ?? '',
        ]])->all();
    }
}
