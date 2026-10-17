<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Actions\RegisterTenantAction;
use App\Modules\Identity\Contracts\PlatformShops;
use App\Modules\Identity\Data\RegisterTenantData;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\ShopEraser;
use App\Support\Text\SearchText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\PhoneNumber;

final class PlatformShopsService implements PlatformShops
{
    public function __construct(
        private readonly RegisterTenantAction $register,
        private readonly ShopEraser $eraser,
    ) {}

    public function deletionPlan(array $tenantIds): array
    {
        return $this->eraser->plan($tenantIds);
    }

    public function delete(array $tenantIds): array
    {
        return $this->eraser->erase($tenantIds);
    }

    public function open(string $shopName, string $ownerName, string $phone, array $shopTypes, ?array $needs, array $acquisition): array
    {
        $phone = (new PhoneNumber($phone, 'EG'))->formatE164();
        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'الموبايل ده عليه حساب خلاص.']);
        }
        $selectable = array_map(fn (ShopType $t) => $t->value, ShopType::selectable());
        if ($shopTypes === [] || array_diff($shopTypes, $selectable) !== []) {
            throw ValidationException::withMessages(['shop_types' => 'اختار نوع المحل من القايمة.']);
        }
        // Easy to read out on WhatsApp and type on a phone: no look-alike characters.
        $password = Str::password(10, letters: true, numbers: true, symbols: false);
        $password = strtr($password, ['0' => '7', 'O' => 'P', 'o' => 'p', 'l' => 'k', 'I' => 'J', '1' => '8']);
        $types = [];
        foreach ($shopTypes as $t) {
            foreach (ShopType::from($t)->parts() as $part) {
                $types[$part->value] = $part;
            }
        }
        $types = array_values($types);
        $owner = $this->register->handle(new RegisterTenantData(
            shopName: $shopName,
            shopTypes: $types,
            ownerName: $ownerName,
            phone: $phone,
            email: null,
            password: $password,
            branchName: 'الفرع الرئيسي',
            acquisition: $acquisition,
            needs: $needs,
        ));

        return ['tenant_id' => (string) $owner->tenant_id, 'owner_id' => (string) $owner->id, 'phone' => $phone, 'password' => $password];
    }

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
        $logins = User::query()->whereIn('tenant_id', $tenantIds)->selectRaw('tenant_id, max(last_login_at) as at')->groupBy('tenant_id')->pluck('at', 'tenant_id');
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
            'last_sign_in_at' => $iso($logins[$t->id] ?? $devices->get($t->id)?->signed_in),
            'last_seen_at' => $iso($devices->get($t->id)?->seen ?? $devices->get($t->id)?->signed_in),
            'acquisition' => $t->settings['acquisition'] ?? null,
        ]])->all();
    }

    public function signupsBySource(Carbon $since): array
    {
        return DB::table('tenants')
            ->where('created_at', '>=', $since)
            ->selectRaw("coalesce(nullif(settings->'acquisition'->>'source', ''), 'direct') as source, coalesce(settings->'acquisition'->>'medium', '') as medium, coalesce(settings->'acquisition'->>'campaign', '') as campaign, count(*) as shops, json_agg(id) as ids")
            ->groupBy('source', 'medium', 'campaign')
            ->orderByDesc('shops')
            ->get()
            ->map(fn (object $r) => ['source' => $r->source, 'medium' => $r->medium, 'campaign' => $r->campaign, 'shops' => (int) $r->shops, 'tenant_ids' => json_decode($r->ids, true)])
            ->all();
    }
}
