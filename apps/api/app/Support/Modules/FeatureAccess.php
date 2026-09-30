<?php

declare(strict_types=1);

namespace App\Support\Modules;

/**
 * Answers "is this small feature on for this shop?": its module is usable and the owner hasn't
 * switched it away from its default. Implemented by the ModuleManager module.
 */
interface FeatureAccess
{
    public function enabled(string $key, ?string $tenantId = null): bool;

    /** @return array<string, bool> every feature of the shop's usable modules */
    public function all(?string $tenantId = null): array;

    public function forget(string $tenantId): void;
}
