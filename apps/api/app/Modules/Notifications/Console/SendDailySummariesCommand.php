<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Support\Notifier;
use App\Modules\Reports\Contracts\DailySummary;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('notifications:daily-summary')]
#[Description("Send each owner-app shop its day's summary once its summary hour has come")]
final class SendDailySummariesCommand extends Command
{
    public function handle(CurrentTenant $tenant, ModuleAccess $modules, FeatureAccess $features, DailySummary $summary, Notifier $notifier): int
    {
        $now = CarbonImmutable::now('Africa/Cairo');
        $day = $now->toDateString();
        $sent = 0;

        foreach (DB::table('tenants')->orderBy('id')->pluck('id') as $tenantId) {
            $tenantId = (string) $tenantId;
            if (! $modules->enabled('owner_app', $tenantId) || ! $features->enabled('owner_app.daily_summary', $tenantId)) {
                continue;
            }
            if ($now->hour < (int) ($features->setting('owner_app.daily_summary', $tenantId) ?? 23)) {
                continue;
            }
            // Once a day: the row is the lock.
            if (DB::table('daily_summaries_sent')->insertOrIgnore(['tenant_id' => $tenantId, 'day' => $day]) === 0) {
                continue;
            }

            $tenant->runAs($tenantId, function () use ($tenantId, $day, $summary, $notifier): void {
                $data = $summary->for($tenantId, $day);
                $notifier->notify(
                    'owner.daily_summary',
                    $data['invoices'] > 0 ? 'ملخص النهارده: '.number_format($data['net'] / 100).' ج من '.$data['invoices'].' فاتورة' : 'ملخص النهارده: مفيش مبيعات',
                    implode("\n", $data['lines']),
                    'i-lucide-chart-column',
                    '/reports/sales?period=today',
                    'owner_app.alerts',
                );
            });
            $sent++;
        }

        $this->components->info("Sent {$sent} daily summar(ies).");

        return self::SUCCESS;
    }
}
