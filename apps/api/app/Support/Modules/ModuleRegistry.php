<?php

declare(strict_types=1);

namespace App\Support\Modules;

use InvalidArgumentException;

/**
 * Catalog of every module the codebase knows about, keyed by module key.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleManifest> */
    private array $modules = [];

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
    }

    public function has(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function get(string $key): ModuleManifest
    {
        return $this->modules[$key] ?? throw new InvalidArgumentException("Unknown module [{$key}].");
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function all(): array
    {
        $modules = $this->modules;
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
        return array_filter($this->all(), fn (ModuleManifest $m): bool => $m->tier !== ModuleTier::Platform);
    }

    /**
     * Modules that declare a dependency on the given key.
     *
     * @return list<ModuleManifest>
     */
    public function dependentsOf(string $key): array
    {
        return array_values(array_filter($this->modules, fn (ModuleManifest $m): bool => in_array($key, $m->dependsOn, true)));
    }
}
