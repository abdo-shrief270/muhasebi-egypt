<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Events;

use App\Support\Events\DomainEvent;

final class ModuleEnabled extends DomainEvent
{
    public const NAME = 'modules.module_enabled';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $moduleKey,
        public readonly string $source,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
