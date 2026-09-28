<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Listeners;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\Repairs\Models\FaultCategory;
use App\Modules\Repairs\Support\DefaultFaults;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/**
 * First time the repairs module is enabled for a shop, give it the default fault lists.
 */
final class SeedDefaultFaults extends ModuleListener
{
    protected function module(): string
    {
        return 'repairs';
    }

    protected function shouldReact(DomainEvent $event): bool
    {
        return $event instanceof ModuleEnabled && $event->moduleKey === 'repairs';
    }

    protected function react(DomainEvent $event): void
    {
        if (FaultCategory::query()->exists()) {
            return;
        }

        $categorySort = 0;

        foreach (DefaultFaults::all() as $category => $types) {
            $model = FaultCategory::create(['name' => $category, 'sort' => $categorySort++]);

            foreach ($types as $sort => $type) {
                $model->types()->create(['tenant_id' => $model->tenant_id, 'name' => $type, 'sort' => $sort]);
            }
        }
    }
}
