<?php

declare(strict_types=1);

namespace App\Support\Modules;

final readonly class MenuItem
{
    /** Sidebar sections, in display order. The web app holds their titles. */
    public const GROUPS = ['sales', 'stock', 'services', 'reports', 'settings'];

    public function __construct(
        public string $to,
        public string $label,
        public string $icon,
        public ?string $permission = null,
        public string $group = 'sales',
        /** False while the screen behind this entry isn't built yet: the entry stays out of the menu. */
        public bool $ready = true,
    ) {
        if (! in_array($group, self::GROUPS, true)) {
            throw new \InvalidArgumentException("Unknown menu group [{$group}] for {$to}.");
        }
    }

    /**
     * @return array{to: string, label: string, icon: string, permission: string|null, group: string}
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'label' => $this->label,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'group' => $this->group,
        ];
    }
}
