<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Console;

use App\Modules\UsedDevices\Actions\PurgeSellerIdsAction;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('used-devices:purge-ids')]
#[Description('Delete the national ID and card photos of erased used-device sellers once the retention period is over')]
final class PurgeSellerIdsCommand extends Command
{
    public function handle(CurrentTenant $tenant, PurgeSellerIdsAction $action): int
    {
        $total = 0;

        $tenants = UsedDeviceSeller::withoutTenancy()->whereNotNull('erased_at')->whereNull('id_purged_at')->distinct()->orderBy('tenant_id')->pluck('tenant_id');
        foreach ($tenants as $tenantId) {
            $total += $tenant->runAs((string) $tenantId, fn (): int => $action->handle());
        }

        $this->components->info("Purged the ID records of {$total} seller(s).");

        return self::SUCCESS;
    }
}
