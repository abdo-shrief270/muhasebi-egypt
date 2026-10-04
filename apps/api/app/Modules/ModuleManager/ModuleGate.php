<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager;

use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleState;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Cache\Repository as Cache;

final class ModuleGate implements ModuleAccess
{
    private const CACHE_TTL = 600;

    /** @var array<string, array<string, TenantModule>> */
    private array $memo = [];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly CurrentTenant $tenant,
        private readonly Cache $cache,
    ) {}

    public function state(string $key, ?string $tenantId = null): ModuleState
    {
        $module = $this->registry->get($key);

        if ($module->tier->isAlwaysOn()) {
            return ModuleState::Enabled;
        }

        $row = $this->rows($tenantId ?? $this->tenant->idOrFail())[$key] ?? null;

        // Open to every shop by the platform admin: on unless the owner hid it.
        if ($module->freeForAll) {
            return $row?->state === ModuleState::Disabled ? ModuleState::Disabled : ModuleState::Enabled;
        }

        return $row?->effectiveState() ?? ModuleState::NotEntitled;
    }

    /** Usable right now — and built: a module still «قريباً» is off whatever its row says. */
    public function enabled(string $key, ?string $tenantId = null): bool
    {
        return $this->registry->get($key)->available && $this->state($key, $tenantId)->isUsable();
    }

    public function enabledKeys(?string $tenantId = null): array
    {
        $tenantId ??= $this->tenant->idOrFail();

        return array_values(array_filter(
            array_keys($this->registry->all()),
            fn (string $key): bool => $this->enabled($key, $tenantId),
        ));
    }

    public function forget(string $tenantId): void
    {
        unset($this->memo[$tenantId]);
        $this->cache->forget($this->cacheKey($tenantId));
    }

    /**
     * @return array<string, TenantModule>
     */
    private function rows(string $tenantId): array
    {
        // Cache raw attributes, not models: the cache store must not unserialize arbitrary objects.
        return $this->memo[$tenantId] ??= TenantModule::hydrate($this->cache->remember(
            $this->cacheKey($tenantId),
            self::CACHE_TTL,
            fn (): array => TenantModule::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->toBase()
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        ))->keyBy('module_key')->all();
    }

    private function cacheKey(string $tenantId): string
    {
        return "tenants:{$tenantId}:modules";
    }
}
