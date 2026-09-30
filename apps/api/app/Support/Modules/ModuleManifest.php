<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\ServiceProvider;

/**
 * Describes a module. Every app/Modules/{Name}/module.php returns one of these.
 */
final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $dependsOn  keys of modules that must be usable first
     * @param  array<string, string>  $permissions  permission key => Arabic label
     * @param  list<MenuItem>  $menu
     * @param  class-string<ServiceProvider>|null  $provider
     * @param  bool  $available  false while the module's screens are still being built: it stays
     *                           off for every shop (no menu, no permissions, no trial) and shows as «قريباً»
     * @param  list<string>  $shopTypes  optional modules: the shop types it is for (ShopType values). A shop sees
     *                                   the ones matching its types, and they start on trial when it registers.
     *                                   Empty = every shop sees it, none gets it on trial by default.
     * @param  list<string>|null  $trialFor  the shop types that get it on trial at registration, when only some of
     *                                       $shopTypes should (null = all of them)
     * @param  list<Feature>  $features  small switches the owner can turn on or off
     */
    public function __construct(
        public string $key,
        public string $name,
        public ModuleTier $tier,
        public string $description = '',
        public array $dependsOn = [],
        public array $permissions = [],
        public array $menu = [],
        public ?string $provider = null,
        public int $sort = 100,
        public bool $available = true,
        public array $shopTypes = [],
        public ?array $trialFor = null,
        public array $features = [],
    ) {}

    public function isOptional(): bool
    {
        return $this->tier === ModuleTier::Optional;
    }

    /**
     * Whether a shop of these types should see this module (core modules: always).
     *
     * @param  list<string>  $types
     */
    public function isFor(array $types): bool
    {
        return ! $this->isOptional() || $this->shopTypes === [] || array_intersect($this->shopTypes, $types) !== [];
    }

    /**
     * Whether a shop of these types gets it on trial when it registers.
     *
     * @param  list<string>  $types
     */
    public function isSuggestedFor(array $types): bool
    {
        return $this->isOptional() && $this->available && array_intersect($this->trialFor ?? $this->shopTypes, $types) !== [];
    }
}
