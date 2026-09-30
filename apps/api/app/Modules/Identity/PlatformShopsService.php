<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\PlatformShops;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Support\Text\SearchText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

        // Signed-in devices (Sanctum tokens): when one was issued (a sign-in) and last used.
        $devices = DB::table('personal_access_tokens as t')
            ->join('users as u', 'u.id', '=', 't.tokenable_id')
            ->where('t.tokenable_type', (new User)->getMorphClass())
            ->whereIn('u.tenant_id', $tenantIds)
            ->selectRaw('u.tenant_id, max(t.created_at) as signed_in, max(t.last_used_at) as seen')
            ->groupBy('u.tenant_id')->get()->keyBy('tenant_id');
        $iso = fn (?string $at): ?string => $at !== null ? Carbon::parse($at)->toIso8601String() : null;

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
            'last_sign_in_at' => $iso($devices->get($t->id)?->signed_in),
            'last_seen_at' => $iso($devices->get($t->id)?->seen ?? $devices->get($t->id)?->signed_in),
        ]])->all();
    }
}
