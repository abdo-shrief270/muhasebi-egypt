<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Http\Resources\TenantResource;
use App\Modules\Identity\Models\Tenant;
use App\Support\Audit\Auditor;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The kinds of shop (for the registration form: each with the modules it comes with) and
 * changing the signed-in shop's kinds later (owner only).
 */
final class ShopTypeController
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (ShopType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'modules' => array_values(array_map(
                fn (ModuleManifest $m): array => ['key' => $m->key, 'name' => $m->name, 'available' => $m->available],
                array_filter($this->registry->all(), fn (ModuleManifest $m) => $m->isOptional() && in_array($type->value, $m->shopTypes, true)),
            )),
        ], ShopType::selectable())]);
    }

    public function update(Request $request, CurrentTenant $current, Auditor $audit): TenantResource
    {
        $data = $request->validate([
            'shop_types' => ['required', 'array', 'min:1', 'max:5'],
            'shop_types.*' => ['distinct', Rule::in(array_map(fn (ShopType $t) => $t->value, ShopType::selectable()))],
        ], ['shop_types.required' => 'اختار نوع واحد على الأقل.'], ['shop_types' => 'نوع المحل']);

        return DB::transaction(function () use ($data, $current, $audit): TenantResource {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($current->idOrFail());
            $before = ShopType::labels($tenant->types());
            $tenant->setTypes(array_map(fn (string $v) => ShopType::from($v), $data['shop_types']));
            $tenant->save();
            $audit->record('shop.types_changed', "غيّر نوع المحل من «{$before}» لـ «".ShopType::labels($tenant->types()).'»', $tenant, tenantId: $tenant->id);

            return new TenantResource($tenant);
        });
    }
}
