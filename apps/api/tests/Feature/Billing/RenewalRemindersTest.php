<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Notifications\Models\Notification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The owner hears about the end of the trial / subscription before and after it. */
class RenewalRemindersTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private function titles(string $tenantId): array
    {
        return Notification::withoutTenancy()->where('tenant_id', $tenantId)->orderBy('created_at')->pluck('title')->all();
    }

    public function test_reminders_before_and_after_the_end_once_each(): void
    {
        $owner = $this->registerShop(ShopType::Accessories);
        $end = app(Subscriptions::class)->for($owner->tenant_id)->paid_until;
        $ends = CarbonImmutable::parse($end)->setTimezone('Africa/Cairo');

        // Every morning from 8 days before to 2 days after (the job runs daily; twice on one day too).
        foreach (range(-8, 2) as $offset) {
            $this->travelTo($ends->addDays($offset)->setTime(11, 0));
            $this->artisan('billing:remind')->assertSuccessful();
            $this->artisan('billing:remind')->assertSuccessful();
        }

        $this->assertSame([
            'التجربة المجانية فاضل فيه 7 أيام (لحد '.$ends->format('d/m').')',
            'التجربة المجانية فاضل فيه 3 أيام (لحد '.$ends->format('d/m').')',
            'التجربة المجانية فاضل فيه 1 يوم (لحد '.$ends->format('d/m').')',
            'التجربة المجانية بيخلص النهارده',
            'التجربة المجانية خلص',
        ], $this->titles($owner->tenant_id));

        // Only the owner sees them.
        Sanctum::actingAs($owner);
        $this->assertSame(5, $this->getJson('/api/v1/notifications')->json('meta.unread'));
    }

    public function test_paying_starts_a_new_round_and_late_shops_hear_it(): void
    {
        $owner = $this->registerShop(ShopType::Accessories);
        $sub = app(Subscriptions::class)->for($owner->tenant_id);
        $sub->forceFill(['on_trial' => false, 'paid_until' => now()->addDays(3)])->save();
        $this->artisan('billing:remind');
        $this->assertStringStartsWith('الاشتراك فاضل فيه 3 أيام', $this->titles($owner->tenant_id)[0]);

        // Renewed: the next end date has its own reminders.
        $sub->forceFill(['paid_until' => now()->addDays(7)->addHour()])->save();
        $this->artisan('billing:remind');
        $this->assertCount(2, $this->titles($owner->tenant_id));

        // Long overdue: restricted, then suspended.
        $sub->forceFill(['paid_until' => now()->subDays((int) config('billing.grace_days'))->subHour()])->save();
        $this->artisan('billing:remind');
        $this->assertSame('الاشتراك متأخر: البيع شغال بس', last($this->titles($owner->tenant_id)));
    }
}
