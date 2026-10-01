<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager;

use App\Modules\ModuleManager\Models\TenantFeature;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\FeatureDisabled;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Cache\Repository as Cache;

final class FeatureGate implements FeatureAccess
{
    private const CACHE_TTL = 600;

    /** @var array<string, array{on: array<string, bool>, settings: array<string, int|string>}> */
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

    public function ensure(string $key, ?string $tenantId = null): void
    {
        if (! $this->enabled($key, $tenantId)) {
            throw FeatureDisabled::for($key, $this->registry->feature($key));
        }
    }

    public function setting(string $key, ?string $tenantId = null): int|string|null
    {
        return $this->settings($tenantId)[$key] ?? $this->registry->feature($key)?->setting?->default;
    }

    public function all(?string $tenantId = null): array
    {
        return $this->state($tenantId)['on'];
    }

    public function settings(?string $tenantId = null): array
    {
        return $this->state($tenantId)['settings'];
    }

    public function forget(string $tenantId): void
    {
        unset($this->memo[$tenantId]);
        $this->cache->forget(self::cacheKey($tenantId));
    }

    /**
     * The owner's overrides, as plain arrays (never models in the cache).
     *
     * @return array<string, array{enabled: bool, value: string|null, by: string|null}>
     */
    public static function overrides(string $tenantId): array
    {
        return TenantFeature::withoutTenancy()
            ->where('tenant_id', $tenantId)
            ->get(['feature_key', 'enabled', 'value', 'updated_by_name'])
            ->mapWithKeys(fn (TenantFeature $f) => [$f->feature_key => ['enabled' => (bool) $f->enabled, 'value' => $f->value, 'by' => $f->updated_by_name]])
            ->all();
    }

    private static function cacheKey(string $tenantId): string
    {
        return "features:v2:{$tenantId}";
    }

    /** @return array{on: array<string, bool>, settings: array<string, int|string>} */
    private function state(?string $tenantId): array
    {
        $tenantId ??= $this->tenant->idOrFail();

        return $this->memo[$tenantId] ??= $this->resolve($tenantId);
    }

    /** @return array{on: array<string, bool>, settings: array<string, int|string>} */
    private function resolve(string $tenantId): array
    {
        /** @var array<string, array{enabled: bool, value: string|null, by: string|null}> $choices */
        $choices = $this->cache->remember(self::cacheKey($tenantId), self::CACHE_TTL, fn (): array => self::overrides($tenantId));

        $on = [];
        $settings = [];
        foreach ($this->registry->features() as $key => ['module' => $module, 'feature' => $feature]) {
            if (! $this->modules->enabled($module, $tenantId)) {
                $on[$key] = false;

                continue;
            }
            $on[$key] = $choices[$key]['enabled'] ?? $feature->default;
            if ($feature->setting !== null) {
                $settings[$key] = $feature->setting->cast($choices[$key]['value'] ?? null);
            }
        }

        return ['on' => $on, 'settings' => $settings];
    }
}
