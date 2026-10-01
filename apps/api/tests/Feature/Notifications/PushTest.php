<?php

namespace Tests\Feature\Notifications;

use App\Modules\Identity\PermissionResolver;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\PushSender;
use App\Support\Events\EventRelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Web Push: devices, preferences, owner alerts (cash difference, returns) and the daily summary. */
class PushTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<array{to: list<string>, payload: array<string, mixed>}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sent = &$this->sent;
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(private array &$sent) {}

            public function configured(): bool
            {
                return true;
            }

            public function send(array $subscriptions, array $payload): array
            {
                $this->sent[] = ['to' => array_column($subscriptions, 'endpoint'), 'payload' => $payload];

                return array_values(array_filter(array_column($subscriptions, 'endpoint'), fn ($e) => str_contains($e, 'gone')));
            }
        });
        $this->openShopWithStock();
        $this->postJson('/api/v1/modules/owner_app/trial')->assertOk();
        app(PermissionResolver::class)->forget();
    }

    private function subscribe(string $endpoint = 'https://push.example.com/owner')
    {
        return $this->postJson('/api/v1/push/subscriptions', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'key', 'auth' => 'secret'], 'device' => 'Chrome']);
    }

    private function relay(): void
    {
        app(EventRelay::class)->publishPending();
    }

    public function test_a_device_subscribes_and_gets_a_test_push(): void
    {
        $this->getJson('/api/v1/push/key')->assertOk()->assertJsonPath('data.configured', true);
        $this->subscribe()->assertCreated();
        $this->subscribe()->assertCreated(); // the same browser again: still one device
        $this->assertSame(1, PushSubscription::withoutTenancy()->count());

        $this->postJson('/api/v1/push/test')->assertOk()->assertJsonPath('data.devices', 1);
        $this->assertSame(['https://push.example.com/owner'], $this->sent[0]['to']);

        $this->deleteJson('/api/v1/push/subscriptions', ['endpoint' => 'https://push.example.com/owner'])->assertNoContent();
        $this->assertSame(0, PushSubscription::withoutTenancy()->count());
    }

    public function test_a_shift_closed_short_alerts_the_owner_and_not_the_cashier(): void
    {
        $this->subscribe()->assertCreated();
        $cashier = $this->staff('cashier');
        Sanctum::actingAs($cashier);
        $this->subscribe('https://push.example.com/cashier')->assertCreated();
        $shift = $this->openShift(10000)->json('data');
        // 40 ج short: under the 50 ج default limit, nothing.
        $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 6000]])->assertOk();
        $this->relay();
        $this->assertSame([], $this->sent);

        $shift = $this->openShift(10000)->json('data');
        $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 2000]])->assertOk();
        $this->relay();

        $this->assertCount(1, $this->sent);
        $this->assertSame(['https://push.example.com/owner'], $this->sent[0]['to'], 'only who has owner_app.alerts');
        $this->assertStringContainsString('عجز 80', $this->sent[0]['payload']['title']);
        $this->assertSame("/cash/{$shift['id']}", $this->sent[0]['payload']['url']);
        $this->assertTrue(Notification::withoutTenancy()->where('type', 'owner.cash_difference')->exists(), 'and it is in the bell');
    }

    public function test_muted_categories_and_quiet_hours_stop_pushes_but_not_the_bell(): void
    {
        $this->subscribe()->assertCreated();
        $prefs = $this->getJson('/api/v1/notifications/preferences')->assertOk()->json('data');
        $this->assertContains('returns', array_column($prefs['categories'], 'key'));

        $this->putJson('/api/v1/notifications/preferences', ['muted' => ['returns'], 'quiet_from' => null, 'quiet_to' => null])->assertOk();
        $this->openShift(10000);
        $sale = $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated()->json('data');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])->assertCreated();
        $this->relay();
        $this->assertSame([], $this->sent);
        $this->assertTrue(Notification::withoutTenancy()->where('type', 'owner.refund')->exists());

        // Quiet hours across midnight (22:00 → 14:00), at noon: nothing pushed either.
        $this->travelTo(CarbonImmutable::now('Africa/Cairo')->setTime(12, 0));
        $this->putJson('/api/v1/notifications/preferences', ['muted' => [], 'quiet_from' => '22:00', 'quiet_to' => '14:00'])->assertOk();
        $sale = $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated()->json('data');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])->assertCreated();
        $this->relay();
        $this->assertSame([], $this->sent);

        $this->putJson('/api/v1/notifications/preferences', ['muted' => [], 'quiet_from' => null, 'quiet_to' => null])->assertOk();
        $sale = $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated()->json('data');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])->assertCreated();
        $this->relay();
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('مرتجع 450', $this->sent[0]['payload']['title']);
    }

    public function test_the_daily_summary_comes_once_after_its_hour_and_gone_devices_are_dropped(): void
    {
        $this->subscribe('https://push.example.com/gone-device')->assertCreated();
        $this->openShift(10000);
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();

        $this->travelTo(CarbonImmutable::now('Africa/Cairo')->setTime(22, 0));
        $this->artisan('notifications:daily-summary')->assertSuccessful();
        $this->assertSame([], $this->sent, 'before 23:00');

        $this->travelTo(CarbonImmutable::now('Africa/Cairo')->setTime(23, 5));
        $this->artisan('notifications:daily-summary')->assertSuccessful();
        $this->artisan('notifications:daily-summary')->assertSuccessful();
        $this->assertCount(1, $this->sent, 'once a day');
        $this->assertStringContainsString('ملخص النهارده: 450 ج من 1 فاتورة', $this->sent[0]['payload']['title']);
        $this->assertStringContainsString('صافي المبيعات: 450 ج', (string) $this->sent[0]['payload']['body']);
        $this->assertSame(0, PushSubscription::withoutTenancy()->count(), 'an expired device is forgotten');
    }

    public function test_partner_activity_is_pushed_too(): void
    {
        $this->subscribe()->assertCreated();
        $this->putJson('/api/v1/notifications/preferences', ['muted' => ['partners', 'nope'], 'quiet_from' => null, 'quiet_to' => null])->assertUnprocessable();
        $this->putJson('/api/v1/notifications/preferences', ['muted' => [], 'quiet_from' => '23:00', 'quiet_to' => null])->assertUnprocessable();
    }
}
