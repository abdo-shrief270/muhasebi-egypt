<?php

declare(strict_types=1);

namespace App\Support\Modules;

final readonly class MenuItem
{
    public function __construct(
        public string $to,
        public string $label,
        public string $icon,
        public ?string $permission = null,
    ) {}

    /**
     * @return array{to: string, label: string, icon: string, permission: string|null}
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'label' => $this->label,
            'icon' => $this->icon,
            'permission' => $this->permission,
        ];
    }
}
