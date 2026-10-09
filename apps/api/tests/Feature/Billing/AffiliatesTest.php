<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCommission;
use App\Modules\Billing\Models\AffiliateReferral;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Models\User;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The partner program: partners' own accounts, shops they bring, their share of payments, payouts. */
class AffiliatesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
        config(['billing.affiliates.rate_percent' => 20, 'billing.affiliates.months' => 12, 'billing.affiliates.hold_days' => 14, 'billing.affiliates.min_payout' => 10000]);
    }

    private function partner(array $overrides = []): Affiliate
    {
        $code = $this->postJson('/api/v1/affiliates/register', [
            'name' => 'كريم', 'phone' => '01223334444', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            'code' => 'karim', 'channel' => 'صفحة فيسبوك', 'terms' => true, ...$overrides,
        ])->assertCreated()->json('code');

        return Affiliate::query()->where('code', $code)->firstOrFail();
    }

    private function shopFrom(?string $code, string $phone = '01099990000'): User
    {
        $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل الصاحب', 'shop_types' => ['accessories'], 'owner_name' => 'سامي', 'phone' => $phone,
            'password' => 'password123', 'password_confirmation' => 'password123', 'affiliate_code' => $code,
        ])->assertCreated();
        app(EventRelay::class)->publishPending();

        return User::query()->where('phone', '+2'.$phone)->firstOrFail();
    }

    private function adminActivates(User $owner, int $amount): BillingInvoice
    {
        Sanctum::actingAs($this->admin, ['admin']);
        $this->postJson("/api/v1/admin/shops/{$owner->tenant_id}/activate", ['plan' => 'accessories', 'cycle' => 'monthly', 'amount' => $amount])->assertOk();

        return BillingInvoice::withoutTenancy()->where('tenant_id', $owner->tenant_id)->latest('paid_at')->firstOrFail();
    }

    public function test_partners_sign_up_and_in_with_their_own_account(): void
    {
        $this->postJson('/api/v1/affiliates/register', ['name' => 'x', 'phone' => '01223334444', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertUnprocessable()->assertJsonValidationErrors('terms');
        $partner = $this->partner();
        $this->assertSame(['KARIM', '+201223334444', 'active'], [$partner->code, $partner->phone, $partner->status]);
        // The code they wanted is taken: they get another one.
        $other = $this->partner(['phone' => '01223335555', 'code' => 'KARIM']);
        $this->assertNotSame('KARIM', $other->code);

        $this->postJson('/api/v1/affiliates/login', ['phone' => '01223334444', 'password' => 'wrong'])->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
        $token = $this->postJson('/api/v1/affiliates/login', ['phone' => '01223334444', 'password' => 'secret-pass'])->assertOk()->json('token');

        $me = $this->withToken($token)->getJson('/api/v1/affiliates/me')->assertOk()->json('data');
        $this->assertSame('https://muhasebi.com/?aff=KARIM', $me['links']['site']);
        $this->assertSame([20, 12, 14], [$me['program']['rate_percent'], $me['program']['months'], $me['program']['hold_days']]);

        // A shop's session can't open a partner's page, and a partner's can't open a shop's.
        $owner = $this->registerShop();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/affiliates/me')->assertForbidden();
        Sanctum::actingAs($partner, ['affiliate']);
        $this->getJson('/api/v1/products')->assertForbidden();
    }

    public function test_a_shop_from_the_link_is_remembered_with_the_welcome_discount(): void
    {
        $partner = $this->partner();
        $shop = $this->shopFrom('karim');

        $referral = AffiliateReferral::query()->where('tenant_id', $shop->tenant_id)->firstOrFail();
        $this->assertSame($partner->id, $referral->affiliate_id);
        $welcome = CouponRedemption::withoutTenancy()->where('tenant_id', $shop->tenant_id)->firstOrFail();
        $this->assertSame(['affiliate', 'percent', 20], [$welcome->source, $welcome->kind, $welcome->value]);

        // Unknown codes don't stop a registration; a partner's own shop earns them nothing.
        $this->assertNull(AffiliateReferral::query()->where('tenant_id', $this->shopFrom('NOPE99', '01099990001')->tenant_id)->first());
        $this->assertNull(AffiliateReferral::query()->where('tenant_id', $this->shopFrom('KARIM', '01223334444')->tenant_id)->first());
    }

    public function test_the_partner_earns_a_share_of_real_payments_for_the_window(): void
    {
        $this->partner();
        $shop = $this->shopFrom('KARIM');

        $invoice = $this->adminActivates($shop, 114000);
        $commission = AffiliateCommission::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $base = $invoice->total - $invoice->vat;
        $this->assertSame([$base, 2000, intdiv($base * 2000 + 5000, 10000), 'held'], [$commission->base, $commission->rate_bp, $commission->amount, $commission->state()]);

        // Past the 12 months from the first payment: nothing more.
        $this->travel(13)->months();
        $later = $this->adminActivates($shop, 114000);
        $this->assertNull(AffiliateCommission::query()->where('invoice_id', $later->id)->first());
    }

    public function test_payouts_after_the_hold_paid_or_rejected_by_an_admin(): void
    {
        $partner = $this->partner();
        $shop = $this->shopFrom('KARIM');
        $this->adminActivates($shop, 114000);
        Sanctum::actingAs($partner, ['affiliate']);

        // Still held.
        $this->postJson('/api/v1/affiliates/payouts')->assertUnprocessable()->assertJsonPath('code', 'payout_method_missing');
        $this->putJson('/api/v1/affiliates/me', ['payout_method' => 'instapay', 'payout_account' => 'karim@instapay'])->assertOk();
        $this->postJson('/api/v1/affiliates/payouts')->assertUnprocessable()->assertJsonPath('code', 'payout_below_minimum');

        $this->travel(15)->days();
        $data = $this->postJson('/api/v1/affiliates/payouts')->assertCreated()->json('data');
        $earned = (int) AffiliateCommission::query()->sum('amount');
        $this->assertSame([0, $earned], [$data['balances']['available'], $data['balances']['requested']]);
        $this->postJson('/api/v1/affiliates/payouts')->assertUnprocessable()->assertJsonPath('code', 'payout_pending');

        // Rejected: the money is available again; then paid.
        Sanctum::actingAs($this->admin, ['admin']);
        $payout = $this->getJson('/api/v1/admin/affiliate-payouts')->assertOk()->json('data.0');
        $this->assertSame([$earned, 'karim@instapay'], [$payout['amount'], $payout['account']]);
        $this->postJson("/api/v1/admin/affiliate-payouts/{$payout['id']}/reject", ['note' => 'الرقم غلط'])->assertOk();
        Sanctum::actingAs($partner, ['affiliate']);
        $this->assertSame($earned, $this->getJson('/api/v1/affiliates/me')->json('data.balances.available'));
        $this->postJson('/api/v1/affiliates/payouts')->assertCreated();

        Sanctum::actingAs($this->admin, ['admin']);
        $payout = $this->getJson('/api/v1/admin/affiliate-payouts')->json('data.0');
        $this->postJson("/api/v1/admin/affiliate-payouts/{$payout['id']}/paid", ['reference' => 'IP-555'])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/admin/affiliate-payouts/{$payout['id']}/paid", ['reference' => 'IP-555'])->assertUnprocessable()->assertJsonPath('code', 'payout_closed');

        $row = $this->getJson('/api/v1/admin/affiliates')->assertOk()->json('data.0');
        $this->assertSame([1, 1, $earned, $earned], [$row['signups'], $row['paying'], $row['earned'], $row['paid']]);
        Sanctum::actingAs($partner, ['affiliate']);
        $me = $this->getJson('/api/v1/affiliates/me')->json('data');
        $this->assertSame(['paid', 'محل الصاحب'], [$me['commissions'][0]['state'], $me['referrals'][0]['shop']]);
    }

    public function test_admins_void_a_share_suspend_a_partner_and_set_their_rate(): void
    {
        $partner = $this->partner();
        $shop = $this->shopFrom('KARIM');
        $this->adminActivates($shop, 114000);
        $commission = AffiliateCommission::query()->firstOrFail();

        $this->postJson("/api/v1/admin/affiliate-commissions/{$commission->id}/void", ['reason' => 'الفلوس رجعت للمحل'])->assertOk();
        $this->assertSame('void', $commission->refresh()->status);

        $this->patchJson("/api/v1/admin/affiliates/{$partner->id}", ['rate_percent' => 30, 'status' => 'suspended'])->assertOk()->assertJsonPath('data.rate_percent', 30);
        // A suspended partner earns nothing more.
        $this->adminActivates($shop, 114000);
        $this->assertSame(1, AffiliateCommission::query()->count());
    }

    public function test_the_website_counts_visits_once_per_visitor(): void
    {
        $partner = $this->partner();
        $this->postJson('/api/v1/public/affiliates/karim/click')->assertOk();
        $this->postJson('/api/v1/public/affiliates/KARIM/click')->assertOk();
        $this->assertSame(1, $partner->refresh()->clicks);
    }
}
