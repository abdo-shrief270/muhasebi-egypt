<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Models\BillingCoupon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\SubscriptionStatus;
use App\Modules\Identity\Contracts\PlatformShops;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes test shops from a live server. Without codes it lists every shop; with codes (or --all)
 * it shows what would go and changes nothing; with --force (and a confirmation typed in a terminal)
 * it deletes them for good. A shop that paid real money is refused unless --include-paid. Coupons
 * the shops used count those uses no more; the admins' log records each deletion.
 */
#[Signature('shops:delete
    {codes?* : the shops\' codes, as the admin panel shows them}
    {--all : every shop}
    {--except=* : with --all, keep these codes}
    {--include-paid : also shops that paid real money}
    {--force : really delete (without it nothing changes)}')]
#[Description('List shops, or delete test shops for good (rows, sign-ins, files, marketplace offers)')]
final class DeleteShopsCommand extends Command
{
    public function handle(PlatformShops $platform, AdminLog $log): int
    {
        $shops = $platform->details($platform->searchIds('', 100000));
        $codes = array_map('strtoupper', (array) $this->argument('codes'));
        if ($codes === [] && ! $this->option('all')) {
            $this->listShops($shops);

            return self::SUCCESS;
        }

        $byCode = collect($shops)->keyBy(fn (array $s) => strtoupper($s['code']));
        $named = $this->option('all') ? array_map('strtoupper', (array) $this->option('except')) : $codes;
        $unknown = array_values(array_diff($named, $byCode->keys()->all()));
        if ($unknown !== []) {
            $this->error('No shop with the code: '.implode(', ', $unknown).'. Run `php artisan shops:delete` to see them.');

            return self::FAILURE;
        }
        $targets = $this->option('all') ? $byCode->except($named) : $byCode->only($codes);
        if ($targets->isEmpty()) {
            $this->info('Nothing to delete.');

            return self::SUCCESS;
        }
        $ids = $targets->pluck('id')->values()->all();

        $paid = $this->paid($ids);
        if ($paid !== [] && ! $this->option('include-paid')) {
            $this->error('These shops paid real money: '.$targets->filter(fn (array $s) => isset($paid[$s['id']]))->pluck('code')->implode(', ').' (add --include-paid if they are tests too).');

            return self::FAILURE;
        }

        $this->line('Shops to delete:');
        $this->table(['code', 'name', 'owner phone', 'created'], $targets->map(fn (array $s) => [$s['code'], $s['name'], $s['owner_phone'], substr($s['created_at'], 0, 10)])->values()->all());
        $plan = $platform->deletionPlan($ids);
        arsort($plan['tables']);
        $this->table(['table', 'rows'], collect($plan['tables'])->map(fn (int $n, string $table) => [$table, $n])->values()->all());
        $this->line("Plus {$plan['tokens']} signed-in devices, {$plan['files']} files and the shops' marketplace offers.");

        if (! $this->option('force')) {
            $this->warn('Nothing was deleted. Take a backup first (infra/production/backup.sh), then run this again with --force.');

            return self::SUCCESS;
        }
        if (! $this->confirm('Delete '.count($ids).' shop(s) and everything above for good? There is no undo.')) {
            $this->warn('Stopped: nothing was deleted (run it in a terminal to answer the question).');

            return self::FAILURE;
        }

        $removed = DB::transaction(function () use ($platform, $log, $ids, $targets): array {
            // Uses of a coupon by these shops stop counting against its limit.
            $uses = CouponRedemption::withoutTenancy()->whereIn('tenant_id', $ids)->whereNotNull('coupon_id')
                ->selectRaw('coupon_id, count(*) as n')->groupBy('coupon_id')->pluck('n', 'coupon_id');
            $removed = $platform->delete($ids);
            foreach ($uses as $couponId => $n) {
                BillingCoupon::query()->whereKey($couponId)->update(['redemptions' => DB::raw('greatest(redemptions - '.(int) $n.', 0)')]);
            }
            foreach ($targets as $shop) {
                $log->record('shop_deleted', $shop['id'], null, [
                    'name' => $shop['name'], 'code' => $shop['code'], 'owner_phone' => $shop['owner_phone'], 'rows' => array_sum($removed['tables']),
                ]);
            }

            return $removed;
        });

        $this->info('Deleted '.count($ids).' shop(s): '.array_sum($removed['tables'])." rows, {$removed['tokens']} signed-in devices, {$removed['files']} files.");

        return self::SUCCESS;
    }

    /** @param  array<string, array<string, mixed>>  $shops */
    private function listShops(array $shops): void
    {
        if ($shops === []) {
            $this->info('No shops.');

            return;
        }
        $subscriptions = Subscription::withoutTenancy()->whereIn('tenant_id', array_keys($shops))->get()->keyBy('tenant_id');
        $paid = $this->paid(array_keys($shops));
        $rows = collect($shops)->sortBy('created_at')->map(fn (array $s) => [
            $s['code'],
            $s['name'],
            $s['owner_phone'],
            substr($s['created_at'], 0, 10),
            substr((string) $s['last_seen_at'], 0, 10) ?: '—',
            ($sub = $subscriptions->get($s['id'])) !== null ? SubscriptionStatus::of($sub, now())->value : '—',
            isset($paid[$s['id']]) ? 'yes' : '',
        ])->values()->all();
        $this->table(['code', 'name', 'owner phone', 'created', 'last seen', 'subscription', 'paid'], $rows);
        $this->line('Delete some: php artisan shops:delete CODE1 CODE2 … (or --all --except=CODE). It shows what would go first; --force deletes.');
    }

    /**
     * Shops with an invoice paid in real money (not a free beta, not zero).
     *
     * @param  list<string>  $tenantIds
     * @return array<string, true>
     */
    private function paid(array $tenantIds): array
    {
        return BillingInvoice::withoutTenancy()->whereIn('tenant_id', $tenantIds)->where('method', '!=', 'beta')->where('total', '>', 0)
            ->distinct()->pluck('tenant_id')->mapWithKeys(fn (string $id) => [$id => true])->all();
    }
}
