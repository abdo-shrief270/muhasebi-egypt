<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager;

use App\Modules\ModuleManager\Models\TenantFeature;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Cache\Repository as Cache;

final class FeatureGate implements FeatureAccess
{
    private const CACHE_TTL = 600;

    /** @var array<string, array<string, bool>> */
    private array $memo = [];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleAccess $modules,
        private readonly CurrentTenant $tenant,
        private readonly Cache $cache,
    ) {}

    public function enabled(string $key, ?string $tenantId = null): bool
    {
        return $this->all($tenantId)[$key] ?? false;
    }

    public function all(?string $tenantId = null): array
    {
        $tenantId ??= $this->tenant->idOrFail();

        return $this->memo[$tenantId] ??= $this->resolve($tenantId);
    }

    public function forget(string $tenantId): void
    {
        unset($this->memo[$tenantId]);
        $this->cache->forget("features:{$tenantId}");
    }

    /** @return array<string, bool> */
    private function resolve(string $tenantId): array
    {
        /** @var array<string, bool> $choices */
        $choices = $this->cache->remember("features:{$tenantId}", self::CACHE_TTL, fn (): array => TenantFeature::withoutTenancy()
            ->where('tenant_id', $tenantId)
            ->pluck('enabled', 'feature_key')
            ->map(fn ($v) => (bool) $v)
            ->all());

        $out = [];
        foreach ($this->registry->features() as $key => ['module' => $module, 'feature' => $feature]) {
            $out[$key] = $this->modules->enabled($module, $tenantId) && ($choices[$key] ?? $feature->default);
        }

        return $out;
    }
}
