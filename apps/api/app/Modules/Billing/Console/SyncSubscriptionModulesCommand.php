<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Billing\Support\Subscriptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:sync-modules')]
#[Description('Grant paying shops the modules of their plan that became available since they paid')]
final class SyncSubscriptionModulesCommand extends Command
{
    public function handle(Subscriptions $subscriptions, Pricing $pricing): int
    {
        Subscription::withoutTenancy()->where('on_trial', false)->whereNotNull('plan')->where('paid_until', '>', now())
            ->each(fn (Subscription $s) => $subscriptions->syncModules($s->tenant_id, $pricing->modulesOf((string) $s->plan, $s->modules)));

        return self::SUCCESS;
    }
}
