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

/** «باقة المؤسسين»: the offer platform admins control, installments, founder numbers, the yearly fee. */
class FoundersTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
    }

    /** @param array<string, mixed> $overrides */
    private function openOffer(array $overrides = []): array
    {
        Sanctum::actingAs($this->admin, ['admin']);

        return $this->putJson('/api/v1/admin/founders/offer', [
            'enabled' => true,
            'name' => 'باقة المؤسسين',
            'tagline' => 'لأول 100 محل',
            'seats' => 100,
            'ends_at' => null,
            'plans' => [
                'accessories' => ['enabled' => true, 'price' => 750000, 'annual_fee' => 60000],
                'repair' => ['enabled' => true, 'price' => 1100000, 'annual_fee' => 90000],
                'pro' => ['enabled' => false, 'price' => 0, 'annual_fee' => 0],
                'business' => ['enabled' => true, 'price' => 2100000, 'annual_fee' => 170000],
            ],
            'installments' => [1, 3],
            'perks' => ['سعر ثابت مدى الحياة', '  '],
            'module_discount_percent' => 30,
            'affiliate_rate_percent' => 10,
            'allow_coupons' => false,
            ...$overrides,
        ])->assertOk()->json('data');
    }

    private function request(User $owner, string $plan = 'repair', int $installments = 3, array $modules = [], string $reference = 'IP-1')
    {
        Sanctum::actingAs($owner);

        return $this->postJson('/api/v1/billing/requests', ['plan' => $plan, 'cycle' => 'founder', 'installments' => $installments, 'modules' => $modules, 'reference' => $reference]);
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

    public function test_the_offer_is_closed_until_an_admin_opens_it(): void
    {
        $owner = $this->registerShop();
        $this->getJson('/api/v1/public/plans')->assertOk()->assertJsonPath('data.founders', null);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'founder'])->assertStatus(422)->assertJsonPath('code', 'founders_closed');

        // Shops can't touch it.
        $this->putJson('/api/v1/admin/founders/offer', ['enabled' => true])->assertForbidden();

        $data = $this->openOffer();
        $this->assertTrue($data['open']);
        $this->assertSame(['سعر ثابت مدى الحياة'], $data['offer']['perks']);
        $this->assertSame(100, $data['stats']['seats_left']);

        $public = $this->getJson('/api/v1/public/plans')->assertOk()->json('data.founders');
        $this->assertSame(['accessories', 'repair', 'business'], array_column($public['plans'], 'key'));
        $this->assertSame([1, 3], $public['installments']);
        $this->assertSame(1100000, $public['plans'][1]['price']);
        Sanctum::actingAs($owner);
        $this->assertSame('باقة المؤسسين', $this->getJson('/api/v1/billing')->assertOk()->json('data.founders.name'));

        // A plan the admin left closed, or installments it doesn't offer.
        $this->postJson('/api/v1/billing/quote', ['plan' => 'pro', 'cycle' => 'founder'])->assertStatus(422)->assertJsonPath('code', 'founder_plan_unavailable');
        $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'founder', 'installments' => 4])->assertStatus(422)->assertJsonPath('code', 'founder_installments_invalid');

        // The admin's changes are logged.
        Sanctum::actingAs($this->admin, ['admin']);
        $this->assertSame('founders_offer_updated', $this->getJson('/api/v1/admin/activity')->assertOk()->json('data.0.action'));
    }

    public function test_installments_then_the_yearly_fee_with_locked_terms(): void
    {
        $this->openOffer();
        $owner = $this->registerShop();
        $trialEnd = $this->subscription($owner)->paid_until->copy();

        Sanctum::actingAs($owner);
        $quote = $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'founder', 'installments' => 3, 'modules' => ['services']])->assertOk()->json('data');
        $this->assertSame([4, 366666], [$quote['months'], $quote['founder']['amount']]);
        $this->assertStringContainsString('القسط 1 من 3', $quote['lines'][0]['description']);
        // An extra module: its yearly price for the months covered, 30% off.
        $services = (int) config('billing.modules.services');
        $this->assertSame(intdiv($services * 10 * 4 * 70, 1200), $quote['lines'][1]['amount']);

        $first = $this->request($owner, modules: ['services'])->assertCreated()->json('data');
        $this->assertSame(['plan' => 'repair', 'price' => 1100000, 'annual_fee' => 90000, 'installments' => 3, 'module_discount' => 30], $first['founder']);
        // The admin raises the price meanwhile: the request keeps what it was priced with.
        $this->openOffer(['plans' => ['repair' => ['enabled' => true, 'price' => 1500000, 'annual_fee' => 120000]]]);
        $this->approve($first['id']);

        $s = $this->subscription($owner);
        $this->assertSame([1, 'founder', 'repair', 1100000, 366666, 90000], [$s->founder_number, $s->cycle, $s->founder_plan, $s->founder_price, $s->founder_paid, $s->founder_annual_fee]);
        $this->assertTrue($s->paid_until->equalTo($trialEnd->copy()->addMonthsNoOverflow(4)));
        $this->assertSame(['services'], $s->modules);

        Sanctum::actingAs($owner);
        $founder = $this->getJson('/api/v1/billing')->assertOk()->json('data');
        $this->assertNull($founder['founders']);
        $this->assertSame([1, 733334, 'installment'], [$founder['subscription']['founder']['number'], $founder['subscription']['founder']['remaining'], $founder['subscription']['founder']['next']]);

        // The plan is locked, the price too (not the offer's new one).
        $this->postJson('/api/v1/billing/quote', ['plan' => 'business', 'cycle' => 'founder'])->assertStatus(422)->assertJsonPath('code', 'founder_plan_locked');
        $this->approve($this->request($owner, reference: 'IP-2')->assertCreated()->json('data.id'));
        Sanctum::actingAs($owner);
        $last = $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'founder'])->assertOk()->json('data');
        $this->assertSame([366668, 3], [$last['founder']['amount'], $last['founder']['installment']]);
        $this->approve($this->request($owner, reference: 'IP-3')->assertCreated()->json('data.id'));
        $this->assertSame(1100000, $this->subscription($owner)->founder_paid);

        // Paid off: next comes the yearly fee, a year at a time — even if the admin closes the offer.
        $this->openOffer(['enabled' => false]);
        Sanctum::actingAs($owner);
        $annual = $this->postJson('/api/v1/billing/quote', ['plan' => 'repair', 'cycle' => 'founder'])->assertOk()->json('data');
        $this->assertSame([12, 90000, 'annual'], [$annual['months'], $annual['total'], $annual['founder']['kind']]);
        $this->assertSame('annual', $this->getJson('/api/v1/billing')->json('data.subscription.founder.next'));
    }

    public function test_numbers_and_seats(): void
    {
        $this->openOffer(['seats' => 2, 'installments' => [1]]);
        [$a, $b, $c] = [$this->registerShop(), $this->registerShop(), $this->registerShop()];

        $first = $this->request($a, installments: 1)->assertCreated()->json('data.id');
        // A request waiting for the admin holds its seat.
        $this->request($b, installments: 1)->assertCreated();
        $this->getJson('/api/v1/public/plans')->assertJsonPath('data.founders', null);
        $this->request($c, installments: 1)->assertStatus(422)->assertJsonPath('code', 'founders_closed');

        $this->approve($first);
        $s = $this->subscription($a);
        $this->assertSame([1, 1100000], [$s->founder_number, $s->founder_paid]);
        $this->assertTrue($s->paid_until->greaterThan(now()->addMonths(12)));

        // An admin may still sell one by hand (e.g. cash), past the seats.
        Sanctum::actingAs($this->admin, ['admin']);
        $this->postJson("/api/v1/admin/shops/{$c->tenant_id}/activate", ['plan' => 'accessories', 'cycle' => 'founder', 'installments' => 1])->assertOk()
            ->assertJsonPath('data.subscription.founder.number', 2);

        $admin = $this->getJson('/api/v1/admin/founders')->assertOk()->json('data');
        $this->assertSame([2, 1, 0], [$admin['stats']['founders'], $admin['stats']['pending'], $admin['stats']['seats_left']]);
        $this->assertSame([1, 2], array_column($admin['founders'], 'number'));
        $this->assertSame(2, $this->getJson('/api/v1/admin/shops?status=founder')->assertOk()->json('meta.total'));

        // A past end date closes it too.
        $this->openOffer(['seats' => null, 'ends_at' => now()->subDay()->toIso8601String()]);
        $this->assertFalse($this->getJson('/api/v1/admin/founders')->json('data.open'));
    }

    public function test_coupons_only_when_the_offer_allows_them(): void
    {
        $this->openOffer(['installments' => [1]]);
        $owner = $this->registerShop();
        BillingCoupon::create(['code' => 'HALF', 'kind' => 'percent', 'value' => 50, 'months' => 12, 'is_active' => true, 'new_shops_only' => false]);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/billing/coupon', ['code' => 'HALF'])->assertOk();

        $this->assertSame(0, $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'founder'])->json('data.discount'));
        $this->openOffer(['installments' => [1], 'allow_coupons' => true]);
        Sanctum::actingAs($owner);
        $this->assertSame(375000, $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'founder'])->json('data.discount'));
    }

    public function test_menu_badges(): void
    {
        $this->openOffer(['installments' => [1]]);
        $this->request($this->registerShop(), installments: 1)->assertCreated();
        Sanctum::actingAs($this->admin, ['admin']);
        $this->getJson('/api/v1/admin/badges')->assertOk()->assertJsonPath('data.payments', 1)->assertJsonPath('data.payouts', 0);
    }
}
