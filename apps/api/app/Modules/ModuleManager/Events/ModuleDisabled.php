<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Events;

use App\Support\Events\DomainEvent;

final class ModuleDisabled extends DomainEvent
{
    public const NAME = 'modules.module_disabled';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $moduleKey,
        public readonly string $reason,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
