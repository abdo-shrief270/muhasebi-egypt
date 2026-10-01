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

    /**
     * Refuses (403 `feature_disabled`) when the feature is off: for actions that check it in code
     * rather than with the `feature:` route middleware.
     */
    public function ensure(string $key, ?string $tenantId = null): void;

    /** The value set next to the switch (its FeatureSetting), or null when it has none. */
    public function setting(string $key, ?string $tenantId = null): int|string|null;

    /** @return array<string, bool> every feature of the shop's usable modules */
    public function all(?string $tenantId = null): array;

    /** @return array<string, int|string> the setting of every feature (of the usable modules) that has one */
    public function settings(?string $tenantId = null): array;

    public function forget(string $tenantId): void;
}
