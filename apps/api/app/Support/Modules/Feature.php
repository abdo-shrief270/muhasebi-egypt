<?php

declare(strict_types=1);

namespace App\Support\Modules;

/**
 * A small switch inside a module the owner can turn on or off for the shop (the "مميزات" page),
 * e.g. importing products from Excel, or hiding prices from a partner until an order is ready.
 * Declared in the module's manifest; checked with FeatureAccess / `feature:{key}` middleware.
 * A switch is shop-wide (off = nobody, the owner included); permissions stay per role.
 *
 * Defaults keep a shop's behaviour unchanged: a switch that removes something is on by default,
 * a new restriction is off. An optional typed setting (FeatureSetting) is edited next to it.
 * `forced` (set by the platform admin): true = on for every shop, false = off for every shop and not
 * listed; either way the owner can't change it.
 */
final readonly class Feature
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $default = true,
        public ?FeatureSetting $setting = null,
        public ?bool $forced = null,
    ) {}

    /** A copy with some fields changed (the platform admin's overrides). */
    public function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'default' => $this->default,
            'setting' => $this->setting?->toArray(),
            'locked' => $this->forced !== null,
        ];
    }
}
