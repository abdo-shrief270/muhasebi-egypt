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
     * @param  list<string>  $permissions
     * @param  list<MenuItem>  $menu
     * @param  class-string<ServiceProvider>|null  $provider
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
    ) {}

    public function isOptional(): bool
    {
        return $this->tier === ModuleTier::Optional;
    }
}
