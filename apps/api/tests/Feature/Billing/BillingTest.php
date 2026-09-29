<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Models\PlatformAdminAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditEntry;
use App\Support\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private PlatformAdmin $admin;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    /** A fresh authenticator code (each step is accepted once). */
    private function code(int $offset = 0): string
    {
        return Totp::code(self::SECRET, Totp::step() + $offset);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->openShopWithStock();
        $this->openShift(10000);
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
        $this->admin->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
    }

    private function asOwner(): static
    {
        Sanctum::actingAs($this->owner);

        return $this;
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs($this->admin, ['admin']);

        return $this;
    }

    private function pay(array $overrides = [])
    {
        return $this->asOwner()->post('/api/v1/billing/requests', [
            'plan' => 'repair',
            'cycle' => 'monthly',
            'reference' => 'IP-889977',
            'sender_name' => 'أحمد',
            'proof' => UploadedFile::fake()->image('transfer.jpg'),
            ...$overrides,
        ], ['Accept' => 'application/json']);
    }

    public function test_a_new_shop_is_on_trial_and_sees_the_plans(): void
    {
        $status = $this->asOwner()->getJson('/api/v1/billing/status')->assertOk()->json('data');
        $this->assertSame(['trialing', true], [$status['status'], $status['paid_up']]);
        $this->assertContains($status['days_left'], [13, 14]);

        $billing = $this->getJson('/api/v1/billing')->assertOk()->json('data');
        $this->assertSame(['accessories', 'repair', 'pro', 'business'], array_column($billing['plans'], 'key'));
        $this->assertSame(449000, collect($billing['plans'])->firstWhere('key', 'repair')['yearly'], '10 months for a year');

        $quote = $this->postJson('/api/v1/billing/quote', ['plan' => 'accessories', 'cycle' => 'monthly', 'modules' => ['repairs', 'shop_orders']])->assertOk()->json('data');
        $this->assertSame([29900 + 14900, ['repairs']], [$quote['total'], $quote['modules']], 'modules already in the plan are not charged again');
        $this->assertSame(5502, $quote['vat'], '14% inside 448 EGP');
    }

    public function test_an_instapay_payment_is_approved_by_an_admin(): void
    {
        $request = $this->pay()->assertCreated()->json('data');
        $this->assertSame(['pending', 44900, true], [$request['status'], $request['amount'], $request['has_proof']]);
        $this->pay()->assertStatus(422)->assertJsonPath('code', 'request_pending');

        // A shop user can't reach the admin side, nor the admin the shop's.
        $this->asOwner()->getJson('/api/v1/admin/payments')->assertForbidden();
        $this->asAdmin()->getJson('/api/v1/billing')->assertForbidden();

        $queue = $this->asAdmin()->getJson('/api/v1/admin/payments')->assertOk()->json('data');
        $this->assertSame(['IP-889977', 'محل 1'], [$queue[0]['reference'], $queue[0]['shop']['name']]);
        $this->assertSame(1, $this->getJson('/api/v1/admin/overview')->json('data.pending_payments'));
        $this->get("/api/v1/admin/payments/{$request['id']}/proof")->assertOk();

        $trialEnd = $this->asOwner()->getJson('/api/v1/billing/status')->json('data.paid_until');
        $this->asAdmin()->postJson("/api/v1/admin/payments/{$request['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $billing = $this->asOwner()->getJson('/api/v1/billing')->json('data');
        $this->assertSame(['active', 'repair', 'monthly'], [$billing['subscription']['status'], $billing['subscription']['plan'], $billing['subscription']['cycle']]);
        $this->assertSame(substr((string) now()->parse($trialEnd)->addMonthNoOverflow()->toIso8601String(), 0, 16), substr($billing['subscription']['paid_until'], 0, 16), 'the paid month starts after the trial');
        $invoice = $billing['invoices'][0];
        $this->assertSame([44900, 5514, 'InstaPay', 'IP-889977'], [$invoice['total'], $invoice['vat'], $invoice['method_label'], $invoice['payment_reference']]);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $invoice['reference']);

        // The plan's modules are the shop's now (not just a trial).
        $repairs = collect($this->getJson('/api/v1/modules')->json('data'))->firstWhere('key', 'repairs');
        $this->assertSame('enabled', $repairs['state']);
        $this->assertTrue(AuditEntry::query()->where('action', 'billing.activated')->exists());

        $this->asAdmin()->postJson("/api/v1/admin/payments/{$request['id']}/approve")->assertStatus(422)->assertJsonPath('code', 'request_not_pending');
    }

    public function test_a_payment_can_be_rejected_or_cancelled(): void
    {
        $first = $this->pay()->json('data');
        $this->asAdmin()->postJson("/api/v1/admin/payments/{$first['id']}/reject", [])->assertUnprocessable();
        $this->postJson("/api/v1/admin/payments/{$first['id']}/reject", ['reason' => 'الرقم مش موجود عندنا'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame('trialing', $this->asOwner()->getJson('/api/v1/billing/status')->json('data.status'));

        $second = $this->pay(['reference' => 'IP-2'])->assertCreated()->json('data');
        $this->postJson("/api/v1/billing/requests/{$second['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->pay(['reference' => 'IP-3'])->assertCreated();

        Sanctum::actingAs($this->staff('cashier'));
        $this->post('/api/v1/billing/requests', ['plan' => 'repair', 'cycle' => 'monthly', 'reference' => 'x'], ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson('/api/v1/billing')->assertForbidden();
        $this->getJson('/api/v1/billing/status')->assertOk();
    }

    public function test_an_admin_activates_a_shop_directly_and_lists_shops(): void
    {
        $this->asAdmin()->postJson("/api/v1/admin/shops/{$this->owner->tenant_id}/activate", [
            'plan' => 'pro', 'cycle' => 'yearly', 'amount' => 400000, 'note' => 'عرض الافتتاح',
        ])->assertOk()->assertJsonPath('data.subscription.plan', 'pro');

        $shop = $this->getJson("/api/v1/admin/shops/{$this->owner->tenant_id}")->json('data');
        $this->assertSame(['manual', 400000], [$shop['invoices'][0]['method'], $shop['invoices'][0]['total']]);
        $this->assertSame(-149000, end($shop['invoices'][0]['lines'])['amount'], 'the discount off 5,490 EGP shows on the invoice');
        $this->assertSame(12, $shop['invoices'][0]['months']);

        $list = $this->getJson('/api/v1/admin/shops?status=active')->assertOk()->json('data');
        $this->assertSame(['محل 1'], array_column(array_column($list, 'shop'), 'name'));
        $this->assertSame([], $this->getJson('/api/v1/admin/shops?status=trialing')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/admin/shops?q='.urlencode('محل'))->json('data'));
        $this->assertSame(54900, $this->getJson('/api/v1/admin/overview')->json('data.mrr'));
    }

    public function test_an_unpaid_shop_keeps_selling_until_it_is_suspended(): void
    {
        $sell = fn () => $this->asOwner()->sell([['method' => 'cash', 'amount' => 45000]]);
        $newProduct = fn () => $this->asOwner()->postJson('/api/v1/products', ['name' => 'جديد', 'category_id' => $this->inShop(fn () => Category::query()->value('id')), 'variants' => [['price_retail' => 100]]]);

        $this->travel(15)->days();
        $this->assertSame('past_due', $this->asOwner()->getJson('/api/v1/billing/status')->json('data.status'));
        $sell()->assertCreated();

        $this->travel(7)->days();
        $this->assertSame('restricted', $this->asOwner()->getJson('/api/v1/billing/status')->json('data.status'));
        $sell()->assertCreated();
        $newProduct()->assertStatus(402)->assertJsonPath('code', 'subscription_restricted');

        $this->travel(60)->days();
        $this->assertSame('suspended', $this->asOwner()->getJson('/api/v1/billing/status')->json('data.status'));
        $sell()->assertStatus(402)->assertJsonPath('code', 'subscription_suspended');
        $this->getJson('/api/v1/sales')->assertOk();
        $this->pay()->assertCreated(); // paying always works

        $this->asAdmin()->postJson("/api/v1/admin/shops/{$this->owner->tenant_id}/activate", ['plan' => 'accessories', 'cycle' => 'monthly'])->assertOk();
        $this->asOwner()->getJson('/api/v1/billing/status')->assertJsonPath('data.status', 'active');
    }

    public function test_an_admin_suspends_and_extends(): void
    {
        $this->asAdmin()->postJson("/api/v1/admin/shops/{$this->owner->tenant_id}/suspend", ['reason' => 'مخالفة'])->assertOk();
        $this->asOwner()->sell([['method' => 'cash', 'amount' => 45000]])->assertStatus(402);
        $this->asAdmin()->postJson("/api/v1/admin/shops/{$this->owner->tenant_id}/unsuspend")->assertOk();
        $this->asOwner()->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();

        $before = $this->asOwner()->getJson('/api/v1/billing/status')->json('data.days_left');
        $this->asAdmin()->postJson("/api/v1/admin/shops/{$this->owner->tenant_id}/trial", ['days' => 10])->assertOk();
        $this->assertSame($before + 10, $this->asOwner()->getJson('/api/v1/billing/status')->json('data.days_left'));
    }

    public function test_admin_sign_in(): void
    {
        $this->app['auth']->forgetGuards(); // real tokens below, not the owner set up by actingAs
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'wrong', 'code' => $this->code()])->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => '000000'])->assertUnprocessable();
        $code = $this->code();
        $token = $this->postJson('/api/v1/admin/auth/login', ['email' => 'ADMIN@muhasebi.test', 'password' => 'secret-password', 'code' => $code])->assertOk()->json('token');
        // The same code can't be used twice.
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => $code])->assertUnprocessable();
        $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertOk()->assertJsonPath('data.email', 'admin@muhasebi.test');
        // An admin token is not a shop session.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();

        // No authenticator set up: no way in.
        $this->admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => $this->code(1)])->assertForbidden()->assertJsonPath('code', 'two_factor_required');

        $this->admin->update(['is_active' => false]);
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => $this->code(1)])->assertUnprocessable();
    }

    public function test_the_admin_api_lives_only_on_its_domain_and_ips(): void
    {
        config(['billing.admin.domain' => 'admin.muhasebi.test']);
        $this->asAdmin()->getJson('/api/v1/admin/overview')->assertNotFound();
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => $this->code()])->assertNotFound();
        $this->getJson('http://admin.muhasebi.test/api/v1/admin/overview')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        config(['billing.admin.allowed_ips' => ['10.0.0.0/8']]);
        $this->getJson('http://admin.muhasebi.test/api/v1/admin/overview')->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->getJson('http://admin.muhasebi.test/api/v1/admin/overview')->assertOk();
    }

    public function test_admin_sign_in_is_locked_after_wrong_passwords_and_tokens_expire(): void
    {
        $this->app['auth']->forgetGuards();
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => "wrong-{$i}", 'code' => '123456'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => $this->code()])
            ->assertStatus(429)->assertJsonPath('code', 'login_locked');

        $this->travel(16)->minutes();
        $token = $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@muhasebi.test', 'password' => 'secret-password', 'code' => $this->code()])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/v1/admin/overview')->assertOk();

        $this->travel(9)->hours();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/overview')->assertUnauthorized();

        // A token without the admin ability is not enough.
        $this->app['auth']->forgetGuards();
        $plain = $this->admin->createToken('other', ['read'])->plainTextToken;
        $this->withToken($plain)->getJson('/api/v1/admin/overview')->assertForbidden();
    }

    public function test_admin_actions_are_logged(): void
    {
        $request = $this->pay()->json('data');
        $this->asAdmin()->postJson("/api/v1/admin/payments/{$request['id']}/approve")->assertOk();
        $this->postJson("/api/v1/admin/shops/{$this->owner->tenant_id}/suspend", ['reason' => 'اختبار'])->assertOk();

        $log = $this->getJson('/api/v1/admin/activity')->assertOk()->json('data');
        $this->assertSame(['shop_suspended', 'payment_approved'], array_column(array_slice($log, 0, 2), 'action'));
        $this->assertSame('الإدارة', $log[0]['admin_name']);
        $this->expectException(\LogicException::class);
        PlatformAdminAction::query()->first()->delete();
    }
}
