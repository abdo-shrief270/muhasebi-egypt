<?php

declare(strict_types=1);

namespace App\Support\Modules;

/**
 * A small switch inside a module the owner can turn on or off for the shop (the "مميزات" page),
 * e.g. importing products from Excel, or hiding prices from a partner until an order is ready.
 * Declared in the module's manifest; checked with FeatureAccess / `feature:{key}` middleware.
 */
final readonly class Feature
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $default = true,
    ) {}

    /** @return array{key: string, label: string, description: string, default: bool} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'description' => $this->description, 'default' => $this->default];
    }
}
