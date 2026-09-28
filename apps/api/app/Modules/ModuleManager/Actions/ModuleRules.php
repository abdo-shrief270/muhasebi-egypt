<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Actions;

use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;

/**
 * Shared checks for changing a shop's modules.
 */
final class ModuleRules
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleAccess $access,
    ) {}

    public function optionalModule(string $key): ModuleManifest
    {
        if (! $this->registry->has($key)) {
            throw new DomainRuleException('القسم غير موجود.', 'module_unknown', 404);
        }

        $module = $this->registry->get($key);

        if (! $module->isOptional()) {
            throw new DomainRuleException("قسم «{$module->name}» أساسي ولا يمكن تغييره.", 'module_not_optional');
        }

        return $module;
    }

    public function assertDependenciesUsable(ModuleManifest $module, string $tenantId): void
    {
        $missing = array_values(array_filter(
            $module->dependsOn,
            fn (string $key): bool => ! $this->access->enabled($key, $tenantId),
        ));

        if ($missing !== []) {
            $names = implode('، ', array_map(fn (string $key): string => $this->registry->get($key)->name, $missing));

            throw new DomainRuleException(
                "لازم تفعّل الأول: {$names}.",
                'module_dependencies_missing',
                context: ['missing' => $missing],
            );
        }
    }

    public function assertNoUsableDependents(ModuleManifest $module, string $tenantId): void
    {
        $dependents = array_values(array_filter(
            $this->registry->dependentsOf($module->key),
            fn (ModuleManifest $dependent): bool => $this->access->enabled($dependent->key, $tenantId),
        ));

        if ($dependents !== []) {
            $names = implode('، ', array_map(fn (ModuleManifest $m): string => $m->name, $dependents));

            throw new DomainRuleException(
                "لا يمكن الإخفاء لأن الأقسام دي معتمدة عليه: {$names}.",
                'module_has_dependents',
                context: ['dependents' => array_map(fn (ModuleManifest $m): string => $m->key, $dependents)],
            );
        }
    }
}
