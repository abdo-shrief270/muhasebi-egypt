<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Identity\Contracts\ShopProfile;
use App\Modules\ModuleManager\Contracts\PlatformModules;
use App\Support\Audit\Auditor;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform admins shape what every shop gets: a module's status (live / «قريباً» / hidden / free for
 * everyone), its trial, the shop types it's for, its name, and each feature switch (the shop decides,
 * or on / off for every shop). Also one shop's modules (open, close, a fresh trial).
 */
final class AdminModuleController
{
    public function __construct(
        private readonly PlatformModules $modules,
        private readonly AdminLog $log,
    ) {}

    public function index(ShopProfile $profile): JsonResponse
    {
        return response()->json(['data' => [
            'modules' => $this->modules->catalog(),
            'shop_types' => $profile->allTypes(),
        ]]);
    }

    public function update(Request $request, string $key, ShopProfile $profile): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['live', 'coming_soon', 'hidden', 'free'])],
            'trial_allowed' => ['sometimes', 'nullable', 'boolean'],
            'auto_trial' => ['sometimes', 'nullable', 'boolean'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:90'],
            'shop_types' => ['sometimes', 'nullable', 'array'],
            'shop_types.*' => ['string', Rule::in(array_keys($profile->allTypes()))],
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);
        $this->modules->updateModule($key, $data, $request->user()?->getAttribute('name'));
        $this->log->record('module_changed', null, null, ['module' => $key, ...$data]);

        return $this->index($profile);
    }

    public function feature(Request $request, string $key, ShopProfile $profile): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['sometimes', Rule::in(['shop', 'on', 'off'])],
            'default' => ['sometimes', 'nullable', 'boolean'],
        ]);
        $this->modules->updateFeature($key, $data, $request->user()?->getAttribute('name'));
        $this->log->record('feature_changed', null, null, ['feature' => $key, ...$data]);

        return $this->index($profile);
    }

    public function shop(string $tenant, ShopDirectory $shops): JsonResponse
    {
        abort_if($shops->find($tenant) === null, 404);

        return response()->json(['data' => $this->modules->shopModules($tenant)]);
    }

    public function setShop(Request $request, string $tenant, string $key, ShopDirectory $shops, CurrentTenant $current, Auditor $audit): JsonResponse
    {
        abort_if($shops->find($tenant) === null, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['open', 'close', 'trial'])]]);
        $current->runAs($tenant, function () use ($tenant, $key, $data, $audit): void {
            $this->modules->setShopModule($tenant, $key, $data['action']);
            $label = ['open' => 'فتحت', 'close' => 'قفلت', 'trial' => 'بدأت تجربة جديدة لـ'][$data['action']];
            $name = app(ModuleRegistry::class)->get($key)->name;
            $audit->record('modules.admin_'.$data['action'], "الإدارة {$label} قسم «{$name}»", null, ['module' => $key], tenantId: $tenant);
        });
        $this->log->record('shop_module_'.$data['action'], $tenant, null, ['module' => $key]);

        return response()->json(['data' => $this->modules->shopModules($tenant)]);
    }
}
