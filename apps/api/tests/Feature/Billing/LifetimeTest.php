<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\BillingCoupon;
use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** «مدى الحياة» and the prices platform admins control: buy once (in installments), then yearly maintenance. */
class LifetimeTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
    }

    /** @param array<string, mixed> $overrides */
    private function settings(array $overrides = []): array
    {
        Sanctum::actingAs($this->admin, ['admin']);

        return $this->putJson('/api/v1/admin/pricing/settings', [
            'yearly_enabled' => false,
            'yearly_months' => 10,
            'lifetime_enabled' => true,
            'lifetime_name' => 'مدى الحياة',
            'lifetime_tagline' => 'اشتري مرة واحدة',
            'lifetime_installments' => [1, 3],
            'lifetime_perks' => ['سعر ثابت', '  '],
            'lifetime_affiliate_rate_percent' => 10,
            'lifetime_allow_coupons' => false,
            'launch_discount_percent' => 0,
            'launch_seats' => null,
            'launch_ends_at' => null,
            ...$overrides,
        ])->assertOk()->json('data');
    }

    private function request(User $owner, string $plan = 'repair', int $installments = 3, array $modules = [], string $reference = 'IP-1')
    {
        Sanctum::actingAs($owner);

        return $this->postJson('/api/v1/billing/requests', ['plan' => $plan, 'cycle' => 'lifetime', 'installments' => $installments, 'modules' => $modules, 'reference' => $reference]);
    }

    private function approve(string $requestId): void
    {
        Sanctum::actingAs($this->admin, ['admin']);
        $this->postJson("/api/v1/admin/payments/{$requestId}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    private function subscription(User $owner): Subscription
    {
        return app(Subscriptions::class)->for($owner->tenant_id)->refresh();
    }

    public function test_admins_set_every_price_and_shops_see_them(): void
    {
        $owner = $this->registerShop();
        Sanctum::actingAs($this->admin, ['admin']);
        $pricing = $this->getJson('/api/v1/admin/pricing')->assertOk()->json('data');
        $this->assertSame([34900, 600000, 150000], [$pricing['plans'][0]['monthly'], $pricing['plans'][0]['lifetime'], $pricing['plans'][0]['maintenance']]);
        // A module without its own lifetime price: 17 months of it, and 4 a year (to 50 ج).
        $repairs = collect($pricing['modules'])->firstWhere('key', 'repairs');
        $this->assertSame([14900, 255000, 60000], [$repairs['monthly'], $repairs['lifetime'], $repairs['maintenance']]);

        // Shops can't touch them.
        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/admin/pricing/catalog', [])->assertForbidden();

        $plans = collect($pricing['plans'])->keyBy('key')->map(fn ($p) => collect($p)->except('key')->all())->all();
        $plans['accessories']['monthly'] = 39900;
        $plans['business']['enabled'] = false;
        $modules = collect($pricing['modules'])->keyBy('key')->map(fn ($m) => collect($m)->only(['monthly', 'lifetime', 'maintenance', 'enabled'])->all())->all();
        $modules['repairs']['lifetime'] = 200000;
        Sanctum::actingAs($this->admin, ['admin']);
        $this->putJson('/api/v1/admin/pricing/catalog', ['plans' => $plans, 'modules' => $modules])->assertOk();
        $this->assertSame('prices_updated', $this->getJson('/api/v1/admin/activity')->json('data.0.action'));

        $public = $this->getJson('/api/v1/public/plans')->assertOk()->json('data');
        $this->assertSame(['accessories', 'repair', 'pro'], array_column($public['plans'], 'key'));
        $this->assertSame(39900, $public['plans'][0]['monthly']);
        $this->assertFalse($public['yearly']);
        $this->assertNull($public['lifetime'], 'off until an admin switches it on');

        // Yearly is off: shops can't choose it; a closed plan can't be chosen either.
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'yearly'])->assertUnprocessable()->assertJsonValidationErrors('cycle');
        $this->postJson('/api/v1/billing/quote', ['plan' => 'business', 'cycle' => 'monthly'])->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->assertSame(39900, $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'monthly'])->assertOk()->json('data.total'));
        $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'lifetime'])->assertStatus(422)->assertJsonPath('code', 'lifetime_closed');

        $this->settings(['yearly_enabled' => true]);
        Sanctum::actingAs($owner);
        $this->assertSame(399000, $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'yearly'])->assertOk()->json('data.total'));

        $data = $this->settings();
        $this->assertTrue($data['open']);
        $this->assertSame(['سعر ثابت'], $data['settings']['lifetime_perks']);
        $offer = $this->getJson('/api/v1/public/plans')->json('data.lifetime');
        $this->assertSame([1, 3], $offer['installments']);
        $this->assertSame([600000, 150000], [$offer['plans'][0]['lifetime'], $offer['plans'][0]['maintenance']]);
        Sanctum::actingAs($owner);
        $this->assertSame('مدى الحياة', $this->getJson('/api/v1/billing')->assertOk()->json('data.lifetime.name'));
        $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'lifetime', 'installments' => 4])->assertStatus(422)->assertJsonPath('code', 'lifetime_installments_invalid');
    }

    public function test_installments_then_yearly_maintenance_with_locked_prices(): void
    {
        $this->settings();
        $owner = $this->registerShop();
        $trialEnd = $this->subscription($owner)->paid_until->copy();

        Sanctum::actingAs($owner);
        // Repair for life (8,500) + online store for life (149 × 17 = 2,533 → 2,550): 3 installments.
        $quote = $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'lifetime', 'installments' => 3, 'modules' => ['online_store']])->assertOk()->json('data');
        $this->assertSame([4, 368333, ['online_store']], [$quote['months'], $quote['total'], $quote['modules']]);
        $this->assertStringContainsString('القسط 1 من 3', $quote['lines'][0]['description']);

        $first = $this->request($owner, modules: ['online_store'])->assertCreated()->json('data');
        $this->assertSame(['plan' => 'repair', 'price' => 1105000, 'maintenance' => 240000, 'installments' => 3, 'modules' => ['online_store'], 'launch_discount' => 0], $first['lifetime']);
        $this->approve($first['id']);

        $s = $this->subscription($owner);
        $this->assertSame([1, 'lifetime', 'repair', 1105000, 368333, 240000], [$s->lifetime_number, $s->cycle, $s->lifetime_plan, $s->lifetime_price, $s->lifetime_paid, $s->lifetime_maintenance]);
        $this->assertTrue($s->paid_until->equalTo($trialEnd->copy()->addMonthsNoOverflow(4)));
        $this->assertSame(['online_store'], $s->modules);

        Sanctum::actingAs($owner);
        $billing = $this->getJson('/api/v1/billing')->assertOk()->json('data');
        $this->assertNull($billing['lifetime']);
        $this->assertSame([1, 736667, 'installment'], [$billing['subscription']['lifetime']['number'], $billing['subscription']['lifetime']['remaining'], $billing['subscription']['lifetime']['next']]);

        // Locked: the plan, and no monthly subscription instead.
        $this->postJson('/api/v1/billing/quote', ['plan' => 'pro', 'cycle' => 'lifetime'])->assertStatus(422)->assertJsonPath('code', 'lifetime_plan_locked');
        $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'monthly'])->assertStatus(422)->assertJsonPath('code', 'lifetime_shop');

        $this->approve($this->request($owner, reference: 'IP-2')->assertCreated()->json('data.id'));
        Sanctum::actingAs($owner);
        $last = $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'lifetime'])->assertOk()->json('data');
        $this->assertSame([368334, 3], [$last['lifetime']['amount'], $last['lifetime']['installment']]);
        $this->approve($this->request($owner, reference: 'IP-3')->assertCreated()->json('data.id'));
        $this->assertSame(1105000, $this->subscription($owner)->lifetime_paid);

        // Paid: next comes a year's maintenance — even with the option switched off. A module added
        // now costs its lifetime price and adds its maintenance.
        $this->settings(['lifetime_enabled' => false]);
        Sanctum::actingAs($owner);
        $maintenance = $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'lifetime', 'modules' => ['online_store', 'services']])->assertOk()->json('data');
        $this->assertSame([12, 240000 + 135000, 'maintenance'], [$maintenance['months'], $maintenance['total'], $maintenance['lifetime']['kind']]);
        $this->approve($this->request($owner, modules: ['online_store', 'services'], reference: 'IP-4')->assertCreated()->json('data.id'));
        $s = $this->subscription($owner);
        $this->assertSame([1105000 + 135000, 1105000 + 135000, 240000 + 30000], [$s->lifetime_price, $s->lifetime_paid, $s->lifetime_maintenance]);
        $this->assertEqualsCanonicalizing(['online_store', 'services'], $s->modules);
    }

    public function test_launch_discount_numbers_and_selling_by_hand(): void
    {
        $this->settings(['lifetime_installments' => [1], 'launch_discount_percent' => 20, 'launch_seats' => 1]);
        [$a, $b] = [$this->registerShop(), $this->registerShop()];

        $first = $this->request($a, installments: 1)->assertCreated()->json('data');
        $this->assertSame([680000, 170000], [$first['lifetime']['price'], $first['lifetime']['launch_discount']]);
        // The only launch seat is held by the request waiting for the admin.
        Sanctum::actingAs($b);
        $this->assertSame(850000, $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'lifetime', 'installments' => 1])->json('data.total'));
        $this->approve($first['id']);
        $this->assertSame([1, 680000], [$this->subscription($a)->lifetime_number, $this->subscription($a)->lifetime_paid]);

        // An admin sells it by hand (e.g. cash) even while it's switched off.
        $this->settings(['lifetime_enabled' => false]);
        $this->postJson("/api/v1/admin/shops/{$b->tenant_id}/activate", ['plan' => 'accessories', 'cycle' => 'lifetime', 'installments' => 1])->assertOk()
            ->assertJsonPath('data.subscription.lifetime.number', 2);
        $admin = $this->getJson('/api/v1/admin/pricing')->assertOk()->json('data');
        $this->assertSame([2, 680000 + 600000, 150000 + 180000], [$admin['stats']['shops'], $admin['stats']['sold'], $admin['stats']['maintenance_yearly']]);
        $this->assertSame(2, $this->getJson('/api/v1/admin/shops?status=lifetime')->assertOk()->json('meta.total'));
    }

    public function test_coupons_only_when_allowed(): void
    {
        $this->settings(['lifetime_installments' => [1]]);
        $owner = $this->registerShop();
        BillingCoupon::create(['code' => 'HALF', 'kind' => 'percent', 'value' => 50, 'months' => 12, 'is_active' => true, 'new_shops_only' => false]);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/billing/coupon', ['code' => 'HALF'])->assertOk();

        $this->assertSame(0, $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'lifetime'])->json('data.discount'));
        $this->settings(['lifetime_installments' => [1], 'lifetime_allow_coupons' => true]);
        Sanctum::actingAs($owner);
        $this->assertSame(300000, $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'lifetime'])->json('data.discount'));
    }

    public function test_menu_badges(): void
    {
        $this->settings(['lifetime_installments' => [1]]);
        $this->request($this->registerShop(), installments: 1)->assertCreated();
        Sanctum::actingAs($this->admin, ['admin']);
        $this->getJson('/api/v1/admin/badges')->assertOk()->assertJsonPath('data.payments', 1)->assertJsonPath('data.payouts', 0);
    }
}
