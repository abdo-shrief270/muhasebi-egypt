<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Contracts;

/**
 * The platform admin's control over modules and feature switches for every shop at once, and over
 * one shop's modules. Used by the admin API (Billing).
 */
interface PlatformModules
{
    /**
     * Every module shops can see (core + optional) with its status, trial, shop types, how many shops
     * use it, and its feature switches.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array;

    /**
     * @param  array{status?: string|null, trial_allowed?: bool|null, auto_trial?: bool|null, trial_days?: int|null, shop_types?: list<string>|null, name?: string|null, description?: string|null}  $data
     *                                                                                                                                                                                                      null = back to what the module declares
     */
    public function updateModule(string $key, array $data, ?string $byName): void;

    /** @param  array{mode?: string, default?: bool|null}  $data  mode shop|on|off */
    public function updateFeature(string $key, array $data, ?string $byName): void;

    /**
     * One shop's optional modules: state, trial, entitled.
     *
     * @return list<array<string, mixed>>
     */
    public function shopModules(string $tenantId): array;

    /** `open` (granted by the admin), `close` (taken back, trial ended), `trial` (a fresh trial). */
    public function setShopModule(string $tenantId, string $key, string $action): void;
}
