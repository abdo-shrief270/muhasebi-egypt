<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Closure;
use InvalidArgumentException;

/**
 * Catalog of every module the codebase knows about, keyed by module key — as the platform admin
 * shaped it: the manifests with the overrides from `useOverrides()` applied (status, trial, names,
 * shop types, feature switches). Long-running workers reload them every OVERRIDES_TTL seconds.
 */
final class ModuleRegistry
{
    private const OVERRIDES_TTL = 15;

    /** @var array<string, ModuleManifest> the manifests as written */
    private array $modules = [];

    /** @var (Closure(): array{modules?: array<string, array<string, mixed>>, features?: array<string, array<string, mixed>>})|null */
    private ?Closure $overrides = null;

    /** @var array<string, ModuleManifest>|null */
    private ?array $applied = null;

    private float $appliedAt = 0;

    public static function discover(string $modulesPath): self
    {
        $registry = new self;

        foreach (glob($modulesPath.'/*/module.php') ?: [] as $file) {
            $manifest = require $file;

            if (! $manifest instanceof ModuleManifest) {
                throw new InvalidArgumentException("{$file} must return a ".ModuleManifest::class);
            }

            $registry->register($manifest);
        }

        return $registry;
    }

    public function register(ModuleManifest $manifest): void
    {
        $this->modules[$manifest->key] = $manifest;
        $this->applied = null;
    }

    /** @param  Closure(): array{modules?: array<string, array<string, mixed>>, features?: array<string, array<string, mixed>>}  $loader */
    public function useOverrides(Closure $loader): void
    {
        $this->overrides = $loader;
        $this->applied = null;
    }

    /** Re-reads the overrides on next use (after the admin changed them). */
    public function flushOverrides(): void
    {
        $this->applied = null;
    }

    public function has(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function get(string $key): ModuleManifest
    {
        return $this->manifests()[$key] ?? throw new InvalidArgumentException("Unknown module [{$key}].");
    }

    /** The module as its manifest declares it, before the platform admin's overrides. */
    public function declared(string $key): ModuleManifest
    {
        return $this->modules[$key] ?? throw new InvalidArgumentException("Unknown module [{$key}].");
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function all(): array
    {
        $modules = $this->manifests();
        uasort($modules, fn (ModuleManifest $a, ModuleManifest $b): int => [$a->sort, $a->key] <=> [$b->sort, $b->key]);

        return $modules;
    }

    /**
     * Modules the shop can see in its modules page (everything but platform).
     *
     * @return array<string, ModuleManifest>
     */
    public function visible(): array
    {
        return array_filter($this->all(), fn (ModuleManifest $m): bool => $m->tier !== ModuleTier::Platform && ! $m->hidden);
    }

    /**
     * Every permission declared by any module.
     *
     * @return array<string, array{module: string, label: string}>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->all() as $module) {
            foreach ($module->permissions as $key => $label) {
                $permissions[$key] = ['module' => $module->key, 'label' => $label];
            }
        }

        return $permissions;
    }

    /**
     * Every feature switch, keyed by feature key, with the module that declares it.
     *
     * @return array<string, array{module: string, feature: Feature}>
     */
    public function features(): array
    {
        $features = [];
        foreach ($this->all() as $module) {
            foreach ($module->features as $feature) {
                $features[$feature->key] = ['module' => $module->key, 'feature' => $feature];
            }
        }

        return $features;
    }

    public function feature(string $key): ?Feature
    {
        return $this->features()[$key]['feature'] ?? null;
    }

    public function moduleOfPermission(string $permission): ?string
    {
        return $this->permissions()[$permission]['module'] ?? null;
    }

    /**
     * Modules that declare a dependency on the given key.
     *
     * @return list<ModuleManifest>
     */
    public function dependentsOf(string $key): array
    {
        return array_values(array_filter($this->manifests(), fn (ModuleManifest $m): bool => in_array($key, $m->dependsOn, true)));
    }

    /** @return array<string, ModuleManifest> */
    private function manifests(): array
    {
        if ($this->overrides === null) {
            return $this->modules;
        }
        if ($this->applied !== null && microtime(true) - $this->appliedAt < self::OVERRIDES_TTL) {
            return $this->applied;
        }
        $overrides = ($this->overrides)();
        $this->appliedAt = microtime(true);

        return $this->applied = array_map(
            fn (ModuleManifest $m): ModuleManifest => self::apply($m, $overrides['modules'][$m->key] ?? [], $overrides['features'] ?? []),
            $this->modules,
        );
    }

    /**
     * @param  array<string, mixed>  $o  the module's row: status, trial_allowed, auto_trial, trial_days, shop_types, name, description
     * @param  array<string, array<string, mixed>>  $features  feature key => mode (shop|on|off), default
     */
    private static function apply(ModuleManifest $m, array $o, array $features): ModuleManifest
    {
        $changes = [];
        if ($m->isOptional()) {
            match ($o['status'] ?? null) {
                'live' => $changes += ['available' => true],
                'coming_soon' => $changes += ['available' => false],
                'hidden' => $changes += ['available' => false, 'hidden' => true],
                'free' => $changes += ['available' => true, 'freeForAll' => true],
                default => null,
            };
            if (isset($o['trial_allowed'])) {
                $changes['trialAllowed'] = (bool) $o['trial_allowed'];
            }
            if (isset($o['trial_days'])) {
                $changes['trialDays'] = (int) $o['trial_days'];
            }
            if (isset($o['shop_types']) && is_array($o['shop_types'])) {
                $changes['shopTypes'] = array_values($o['shop_types']);
                $changes['trialFor'] = null;
            }
            // auto_trial: null = as declared, true = every shop of its types, false = none.
            if (isset($o['auto_trial'])) {
                $changes['trialFor'] = $o['auto_trial'] ? null : [];
            }
        }
        foreach (['name', 'description'] as $field) {
            if (isset($o[$field]) && $o[$field] !== '') {
                $changes[$field] = (string) $o[$field];
            }
        }
        $switched = false;
        $list = array_map(function (Feature $f) use ($features, &$switched): Feature {
            $row = $features[$f->key] ?? null;
            if ($row === null) {
                return $f;
            }
            $switched = true;

            return $f->with(
                default: isset($row['default']) ? (bool) $row['default'] : $f->default,
                forced: match ($row['mode'] ?? 'shop') {
                    'on' => true, 'off' => false, default => null
                },
            );
        }, $m->features);
        if ($switched) {
            $changes['features'] = $list;
        }

        return $changes === [] ? $m : $m->with(...$changes);
    }
}
