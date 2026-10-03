<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Notifications\Contracts\Notifications;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Time\ShopDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Renewal reminders to the shop's owner (the bell + a push): a week, three days and one day
 * before the subscription or trial ends, on the day, the day after (past due), when it becomes
 * restricted and when it's suspended. Each once per end date — paying moves the end date, so the
 * next period gets its own.
 */
final class RenewalReminders
{
    public function __construct(
        private readonly Notifications $notifications,
        private readonly CurrentTenant $tenant,
    ) {}

    /** @return int how many reminders went out */
    public function send(): int
    {
        $today = ShopDay::today();
        $grace = (int) config('billing.grace_days');
        $suspend = (int) config('billing.suspend_after_days');
        $sent = 0;

        Subscription::withoutTenancy()
            ->whereNull('suspended_at')
            ->whereBetween('paid_until', [now()->subDays($suspend + 1), now()->addDays(8)])
            ->each(function (Subscription $s) use ($today, $grace, $suspend, &$sent): void {
                $ends = Carbon::parse($s->paid_until->copy()->setTimezone(ShopDay::TZ)->toDateString());
                $days = (int) $today->diffInDays($ends, false);
                $step = match (true) {
                    in_array($days, [7, 3, 1, 0], true) => "d{$days}",
                    $days === -1 => 'past_due',
                    $days === -$grace => 'restricted',
                    $days === -$suspend => 'suspended',
                    default => null,
                };
                if ($step === null || ! $this->claim($s->tenant_id, $ends, $step)) {
                    return;
                }
                [$title, $body, $urgent] = $this->message($step, $days, (bool) $s->on_trial, $ends, $grace);
                $this->tenant->runAs($s->tenant_id, fn () => $this->notifications->notify(
                    "billing.{$step}", $title, $body, 'i-lucide-calendar-clock', '/settings/billing', 'owner', $urgent,
                ));
                $sent++;
            });

        return $sent;
    }

    private function claim(string $tenantId, Carbon $ends, string $step): bool
    {
        return DB::table('billing_reminders')->insertOrIgnore([
            'tenant_id' => $tenantId, 'ends_on' => $ends->toDateString(), 'step' => $step, 'sent_at' => now(),
        ]) === 1;
    }

    /** @return array{0: string, 1: string, 2: bool} title, body, urgent */
    private function message(string $step, int $days, bool $trial, Carbon $ends, int $grace): array
    {
        $what = $trial ? 'التجربة المجانية' : 'الاشتراك';
        $date = $ends->format('d/m');
        $pay = 'جدّد من «الاشتراك» وادفع بـ InstaPay، والتفعيل بيتم في نفس اليوم.';

        return match ($step) {
            'd0' => ["{$what} بيخلص النهارده", $pay, true],
            'past_due' => ["{$what} خلص", "كل حاجة شغالة لحد دلوقتي، وبعد {$grace} أيام إضافة الأصناف والموظفين هتتقفل. {$pay}", true],
            'restricted' => ['الاشتراك متأخر: البيع شغال بس', "مينفعش تضيف أصناف أو موظفين أو فروع لحد ما يتجدد. {$pay}", true],
            'suspended' => ['الاشتراك اتوقف', 'البرنامج بقى للفرجة بس. جدّد عشان ترجع تشتغل، وكل بياناتك محفوظة.', true],
            default => ["{$what} فاضل فيه {$days} ".($days === 1 ? 'يوم' : 'أيام')." (لحد {$date})", $trial ? 'اشترك دلوقتي عشان الشغل يكمل من غير توقف.' : $pay, false],
        };
    }
}
