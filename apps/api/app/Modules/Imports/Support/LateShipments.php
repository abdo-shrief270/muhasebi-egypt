<?php

declare(strict_types=1);

namespace App\Modules\Imports\Support;

use App\Modules\Imports\Enums\ShipmentStatus;
use App\Modules\Imports\Models\ImportShipment;
use App\Modules\Notifications\Contracts\Notifications;
use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantScope;
use App\Support\Time\ShopDay;
use Illuminate\Support\Facades\DB;

/**
 * The bell (and a push) for each shipment still on the way after its expected date: once per
 * expected date, so moving the date and missing it again alerts again.
 */
final class LateShipments
{
    public function __construct(
        private readonly Notifications $notifications,
        private readonly ModuleAccess $modules,
        private readonly CurrentTenant $tenant,
    ) {}

    /** @return int how many alerts went out */
    public function alert(): int
    {
        $sent = 0;
        ImportShipment::withoutTenancy()
            ->with(['contact' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->whereIn('status', ShipmentStatus::onTheWay())
            ->where('expected_on', '<', ShopDay::today()->toDateString())
            ->where(fn ($q) => $q->whereNull('late_alerted_for')->orWhereColumn('late_alerted_for', '<>', 'expected_on'))
            ->orderBy('expected_on')
            ->each(function (ImportShipment $s) use (&$sent): void {
                if (! $this->modules->enabled('imports', $s->tenant_id)) {
                    return;
                }
                // Claimed first, so two runs never alert twice.
                $claimed = DB::table('import_shipments')->where('id', $s->id)
                    ->where(fn ($q) => $q->whereNull('late_alerted_for')->orWhereColumn('late_alerted_for', '<>', 'expected_on'))
                    ->update(['late_alerted_for' => DB::raw('expected_on')]);
                if ($claimed !== 1) {
                    return;
                }
                $days = (int) $s->expected_on->diffInDays(ShopDay::today());
                $this->tenant->runAs($s->tenant_id, fn () => $this->notifications->notify(
                    'imports.late',
                    "الشحنة {$s->reference()} متأخرة",
                    "من «{$s->contact?->name}»، كانت متوقعة {$s->expected_on->format('d/m')} ({$days} ".($days === 1 ? 'يوم' : 'أيام').' تأخير). كلّم الشاحن أو عدّل الميعاد.',
                    'i-lucide-ship',
                    "/imports/shipments/{$s->id}",
                    'imports.view',
                ));
                $sent++;
            });

        return $sent;
    }
}
