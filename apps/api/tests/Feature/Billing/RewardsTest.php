<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** Credit, points, invite codes and subscription coupons. */
class RewardsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $inviter;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->inviter = $this->registerShop();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
    }

    private function as(User|PlatformAdmin $who): static
    {
        $who instanceof PlatformAdmin ? Sanctum::actingAs($who, ['admin']) : Sanctum::actingAs($who);

        return $this;
    }

    private function invited(): User
    {
        $code = Tenant::query()->whereKey($this->inviter->tenant_id)->value('code');
        $body = [
            'shop_name' => 'محل جديد', 'shop_types' => ['accessories'], 'owner_name' => 'سامي', 'phone' => '01099990000',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ];
        $this->postJson('/api/v1/auth/register', [...$body, 'referral_code' => 'NOPE99'])->assertUnprocessable()->assertJsonValidationErrors('referral_code');
        $this->postJson('/api/v1/auth/register', [...$body, 'referral_code' => strtolower((string) $code)])->assertCreated();
        app(EventRelay::class)->publishPending();

        return User::query()->where('phone', '+201099990000')->firstOrFail();
    }

    private function request(User $owner, array $overrides = [])
    {
        return $this->as($owner)->postJson('/api/v1/billing/requests', ['plan' => 'accessories', 'cycle' => 'monthly', 'reference' => 'IP-1', ...$overrides]);
    }

    public function test_an_invited_shop_gets_its_welcome_and_the_inviter_points_when_it_pays(): void
    {
        $billing = $this->as($this->inviter)->getJson('/api/v1/billing')->assertOk()->json('data');
        $this->assertSame([0, 0, 0], [$billing['wallet']['credit'], $billing['wallet']['points'], $billing['referral']['joined']]);
        $this->assertStringContainsString('/register?ref=', $billing['referral']['link']);

        $new = $this->invited();
        $this->assertSame(1, $this->as($this->inviter)->getJson('/api/v1/billing')->json('data.referral.joined'));

        // 20% off for 3 months: 299 → 239.20.
        $quote = $this->as($new)->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'monthly'])->json('data');
        $this->assertSame([29900, 5980, 23920, 23920], [$quote['price'], $quote['discount'], $quote['total'], $quote['due']]);
        $this->assertSame('خصم الدعوة (20%)', last($quote['lines'])['description']);

        // No points until the new shop really pays.
        $request = $this->request($new)->assertCreated()->json('data');
        $this->assertSame([23920, 5980], [$request['amount'], $request['discount']]);
        $this->assertSame(0, $this->as($this->inviter)->getJson('/api/v1/billing')->json('data.wallet.points'));

        $this->as($this->admin)->postJson("/api/v1/admin/payments/{$request['id']}/approve")->assertOk();
        $invoice = $this->as($new)->getJson('/api/v1/billing')->json('data.invoices.0');
        $this->assertSame(23920, $invoice['total']);
        $this->assertSame('خصم الدعوة 20% — فاضل 2 شهور', $this->getJson('/api/v1/billing')->json('data.discounts.0.label'));

        $inviter = $this->as($this->inviter)->getJson('/api/v1/billing')->json('data');
        $this->assertSame([500, 1, 'referral'], [$inviter['wallet']['points'], $inviter['referral']['paid'], $inviter['wallet']['history'][0]['type']]);
        $this->assertSame('كسبت 500 نقطة', Notification::withoutTenancy()->where('tenant_id', $this->inviter->tenant_id)->value('title'));

        // A second payment doesn't pay the inviter again.
        $second = $this->request($new, ['reference' => 'IP-2'])->assertCreated()->json('data');
        $this->as($this->admin)->postJson("/api/v1/admin/payments/{$second['id']}/approve")->assertOk();
        $this->assertSame(500, $this->as($this->inviter)->getJson('/api/v1/billing')->json('data.wallet.points'));
    }

    public function test_points_become_credit_that_pays_the_subscription(): void
    {
        $this->as($this->admin)->postJson("/api/v1/admin/shops/{$this->inviter->tenant_id}/wallet", ['unit' => 'points', 'amount' => 3000, 'note' => 'مسابقة'])->assertCreated();
        $this->as($this->inviter)->postJson('/api/v1/billing/points/convert', ['points' => 50])->assertUnprocessable();
        $this->postJson('/api/v1/billing/points/convert', ['points' => 105])->assertUnprocessable();
        $this->postJson('/api/v1/billing/points/convert', ['points' => 4000])->assertUnprocessable()->assertJsonPath('code', 'wallet_insufficient');
        $this->assertSame(10000, $this->postJson('/api/v1/billing/points/convert', ['points' => 1000])->assertOk()->json('data.credit'));

        // 100 ج of 299: the rest by InstaPay; the credit is held, and back if the request is rejected.
        $this->postJson('/api/v1/billing/pay-with-credit', ['plan' => 'accessories', 'cycle' => 'monthly'])->assertUnprocessable()->assertJsonPath('code', 'credit_not_enough');
        $request = $this->request($this->inviter)->assertCreated()->json('data');
        $this->assertSame([19900, 10000], [$request['amount'], $request['credit_used']]);
        $this->assertSame(0, $this->getJson('/api/v1/billing')->json('data.wallet.credit'));
        $this->as($this->admin)->postJson("/api/v1/admin/payments/{$request['id']}/reject", ['reason' => 'مش واصل'])->assertOk();
        $this->assertSame(10000, $this->as($this->inviter)->getJson('/api/v1/billing')->json('data.wallet.credit'));

        // Enough credit: renewed at once, no admin.
        $this->postJson('/api/v1/billing/points/convert', ['points' => 2000])->assertOk();
        $this->request($this->inviter)->assertUnprocessable()->assertJsonPath('code', 'pay_with_credit');
        $invoice = $this->postJson('/api/v1/billing/pay-with-credit', ['plan' => 'accessories', 'cycle' => 'monthly'])->assertCreated()->json('data');
        $this->assertSame([29900, 29900, 'من رصيد الحساب'], [$invoice['total'], $invoice['credit_used'], $invoice['method_label']]);
        $billing = $this->getJson('/api/v1/billing')->json('data');
        $this->assertSame(['active', 100], [$billing['subscription']['status'], $billing['wallet']['credit']]);

        // Renewing before it ends, yearly: points for both.
        $this->as($this->admin)->postJson("/api/v1/admin/shops/{$this->inviter->tenant_id}/wallet", ['unit' => 'credit', 'amount' => 300000, 'note' => 'تعويض'])->assertCreated();
        $this->as($this->inviter)->postJson('/api/v1/billing/pay-with-credit', ['plan' => 'accessories', 'cycle' => 'yearly'])->assertCreated();
        $types = array_column($this->getJson('/api/v1/billing')->json('data.wallet.history'), 'type');
        $this->assertContains('yearly', $types);
        $this->assertContains('early_renewal', $types);
        $this->assertSame(200 + 50, $this->getJson('/api/v1/billing')->json('data.wallet.points'));
    }

    public function test_coupons_from_the_admins(): void
    {
        $this->as($this->admin)->postJson('/api/v1/admin/coupons', ['code' => 'eid', 'kind' => 'percent', 'value' => 50, 'months' => 1, 'new_shops_only' => true])->assertCreated();
        $this->postJson('/api/v1/admin/coupons', ['code' => 'GIFT50', 'kind' => 'credit', 'value' => 5000, 'max_redemptions' => 1])->assertCreated();
        $this->postJson('/api/v1/admin/coupons', ['code' => 'EID', 'kind' => 'amount', 'value' => 100])->assertUnprocessable();
        $this->as($this->inviter)->postJson('/api/v1/admin/coupons', [])->assertForbidden();

        $this->postJson('/api/v1/billing/coupon', ['code' => 'NOPE'])->assertUnprocessable()->assertJsonPath('code', 'coupon_invalid');
        $this->assertSame('رصيد 50 ج في حسابك', $this->postJson('/api/v1/billing/coupon', ['code' => 'gift50'])->assertOk()->json('data.label'));
        $this->postJson('/api/v1/billing/coupon', ['code' => 'GIFT50'])->assertUnprocessable();
        $this->assertSame(5000, $this->getJson('/api/v1/billing')->json('data.wallet.credit'));

        // 50% off one month, minus the 50 ج credit: 299 → 149.50 → 99.50 to transfer.
        $this->postJson('/api/v1/billing/coupon', ['code' => 'EID'])->assertOk();
        $quote = $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'monthly'])->json('data');
        $this->assertSame([14950, 5000, 9950], [$quote['discount'], $quote['credit_used'], $quote['due']]);
        $request = $this->request($this->inviter)->assertCreated()->json('data');
        $this->as($this->admin)->postJson("/api/v1/admin/payments/{$request['id']}/approve")->assertOk();
        $this->assertSame(14950, $this->as($this->inviter)->getJson('/api/v1/billing')->json('data.invoices.0.total'));
        $this->assertSame([], $this->getJson('/api/v1/billing')->json('data.discounts'));

        // «للمحلات الجديدة بس»: a shop that paid can't use it.
        $this->as($this->admin)->postJson('/api/v1/admin/coupons', ['code' => 'NEWONLY', 'kind' => 'percent', 'value' => 10, 'new_shops_only' => true])->assertCreated();
        $this->as($this->inviter)->postJson('/api/v1/billing/coupon', ['code' => 'NEWONLY'])->assertUnprocessable()->assertJsonPath('code', 'coupon_new_shops_only');
        $this->assertSame(1, collect($this->as($this->admin)->getJson('/api/v1/admin/coupons')->json('data'))->firstWhere('code', 'EID')['redemptions']);
    }
}
