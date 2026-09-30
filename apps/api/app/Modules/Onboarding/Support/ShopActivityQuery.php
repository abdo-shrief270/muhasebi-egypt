<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Support;

use App\Modules\Onboarding\Contracts\ShopActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Recent work per shop (read-only SQL over sales / repair tickets, like the Reports module). */
final class ShopActivityQuery implements ShopActivity
{
    public function recent(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }
        $since = now()->subDays(7);

        $sales = DB::table('sales')->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, max(completed_at) as last_at, count(*) filter (where completed_at >= ?) as recent', [$since])
            ->groupBy('tenant_id')->get()->keyBy('tenant_id');
        $repairs = DB::table('repair_tickets')->whereIn('tenant_id', $tenantIds)->where('received_at', '>=', $since)
            ->selectRaw('tenant_id, count(*) as recent')->groupBy('tenant_id')->pluck('recent', 'tenant_id');

        $out = [];
        foreach ($tenantIds as $tenantId) {
            $sale = $sales->get($tenantId);
            $out[$tenantId] = [
                'last_sale_at' => $sale?->last_at !== null ? Carbon::parse($sale->last_at)->toIso8601String() : null,
                'sales_7d' => (int) ($sale->recent ?? 0),
                'repairs_7d' => (int) ($repairs[$tenantId] ?? 0),
            ];
        }

        return $out;
    }
}
