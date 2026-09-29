<?php

declare(strict_types=1);

namespace App\Modules\Customers\Console;

use App\Modules\Customers\Actions\EraseInactiveCustomersAction;
use App\Modules\Customers\Models\CustomerPrivacySetting;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('customers:erase-inactive')]
#[Description("Erase the personal data of customers inactive past their shop's retention period")]
final class EraseInactiveCustomersCommand extends Command
{
    public function handle(CurrentTenant $tenant, EraseInactiveCustomersAction $action): int
    {
        $total = 0;

        CustomerPrivacySetting::withoutTenancy()
            ->whereNotNull('retention_years')
            ->orderBy('tenant_id')
            ->each(function (CustomerPrivacySetting $setting) use ($tenant, $action, &$total): void {
                $total += $tenant->runAs($setting->tenant_id, fn (): int => $action->handle((int) $setting->retention_years));
            });

        $this->components->info("Erased {$total} customer(s).");

        return self::SUCCESS;
    }
}
