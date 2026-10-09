<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Models\PlatformAdminAction;
use App\Support\Audit\AuditEntry;
use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** The platform admin's beta tools: feedback inbox, client errors, shop activity, free beta periods. */
class BetaAdminTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->openShopWithStock();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
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

    /** @return list<string> */
    private function logged(): array
    {
        return PlatformAdminAction::query()->orderBy('created_at')->orderBy('id')->pluck('action')->all();
    }

    public function test_admins_list_feedback_across_shops_and_change_its_status(): void
    {
        $this->asOwner()->post('/api/v1/feedback', [
            'type' => 'problem', 'message' => 'الطباعة بتقف', 'page' => '/pos', 'screenshot' => UploadedFile::fake()->image('s.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/v1/feedback', ['type' => 'suggestion', 'message' => 'عايزين تقرير للفنيين'], ['Accept' => 'application/json'])->assertCreated();
        $other = $this->registerShop();
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/feedback', ['type' => 'question', 'message' => 'إزاي أضيف فرع؟'])->assertCreated();

        // Shops can't read it.
        $this->asOwner()->getJson('/api/v1/admin/feedback')->assertForbidden();

        $res = $this->asAdmin()->getJson('/api/v1/admin/feedback')->assertOk();
        $this->assertSame(3, $res->json('meta.total'));
        $this->assertSame(3, $res->json('meta.new_feedback'));
        $this->assertSame('محل 2', $res->json('data.0.shop.name'));

        $this->assertSame(['suggestion', 'problem'], array_column($this->getJson('/api/v1/admin/feedback?q='.urlencode('محل 1'))->json('data'), 'type'));
        $problem = $this->getJson('/api/v1/admin/feedback?type=problem')->assertOk()->json('data.0');
        $this->assertTrue($problem['has_screenshot']);
        $this->assertSame('مشكلة', $problem['type_label']);

        $this->get("/api/v1/admin/feedback/{$problem['id']}/screenshot")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->patchJson("/api/v1/admin/feedback/{$problem['id']}", ['status' => 'closed'])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/feedback/{$problem['id']}", ['status' => 'done'])->assertOk()
            ->assertJsonPath('data.status', 'done')->assertJsonPath('data.status_changed_by', 'الإدارة');

        $this->assertSame([$problem['id']], array_column($this->getJson('/api/v1/admin/feedback?status=done')->json('data'), 'id'));
        $this->assertSame(2, $this->getJson('/api/v1/admin/overview')->json('data.new_feedback'));
        $this->assertSame(['feedback_screenshot_viewed', 'feedback_status'], $this->logged());
    }

    public function test_admins_see_client_errors_grouped_by_message_and_resolve_them(): void
    {
        $error = ['kind' => 'error', 'message' => 'TypeError: x is undefined', 'page' => '/pos'];
        $this->asOwner()->postJson('/api/v1/client-errors', ['errors' => [$error, ['kind' => 'rejection', 'message' => 'Failed to fetch']]])->assertAccepted();
        $this->postJson('/api/v1/client-errors', ['errors' => [[...$error, 'count' => 2]]])->assertAccepted();
        Sanctum::actingAs($this->registerShop());
        $this->postJson('/api/v1/client-errors', ['errors' => [$error]])->assertAccepted();

        $groups = $this->asAdmin()->getJson('/api/v1/admin/client-errors')->assertOk()->json('data');
        $this->assertCount(2, $groups);
        $type = collect($groups)->firstWhere('message', 'TypeError: x is undefined');
        $this->assertSame(4, $type['count']);
        $this->assertCount(2, $type['shops']);
        $this->assertEqualsCanonicalizing(['محل 1', 'محل 2'], array_column($type['shops'], 'name'));

        $this->postJson("/api/v1/admin/client-errors/{$type['fingerprint']}/resolve", ['resolved' => true])->assertOk();
        $this->assertSame(['Failed to fetch'], array_column($this->getJson('/api/v1/admin/client-errors')->json('data'), 'message'));
        $this->assertCount(2, $this->getJson('/api/v1/admin/client-errors?resolved=1')->json('data'));

        // Happens again → back on the list.
        $this->asOwner()->postJson('/api/v1/client-errors', ['errors' => [$error]])->assertAccepted();
        $this->assertCount(2, $this->asAdmin()->getJson('/api/v1/admin/client-errors')->json('data'));
        $this->postJson('/api/v1/admin/client-errors/'.str_repeat('a', 40).'/resolve', ['resolved' => true])->assertNotFound();
        $this->assertSame(['client_error_resolved'], $this->logged());
    }

    public function test_the_shop_list_shows_activity_and_setup_progress(): void
    {
        $this->openShift();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();

        $row = $this->asAdmin()->getJson('/api/v1/admin/shops')->assertOk()->json('data.0');
        $this->assertSame(1, $row['activity']['sales_7d']);
        $this->assertSame(0, $row['activity']['repairs_7d']);
        $this->assertNotNull($row['activity']['last_sale_at']);
        $this->assertSame(9, $row['setup']['total']);
        $this->assertSame(4, $row['setup']['done']); // products, stock, shift, first sale
        $this->assertContains('ضيف موظف', $row['setup']['missing']);
        $this->assertArrayHasKey('last_sign_in_at', $row['shop']);

        $this->owner->createToken('phone');
        $detail = $this->getJson("/api/v1/admin/shops/{$this->owner->tenant_id}")->assertOk()->json('data');
        $this->assertNotNull($detail['shop']['last_sign_in_at']);
        $this->assertSame(4, $detail['setup']['done']);
        $this->assertSame([], $detail['feedback']);
    }

    public function test_an_admin_grants_a_free_beta_period(): void
    {
        // An accessories shop (no repairs) gets the «pro» plan free for 3 months.
        $shopOwner = $this->registerShop();
        $tenant = $shopOwner->tenant_id;
        $this->assertFalse(app(ModuleAccess::class)->enabled('repairs', $tenant));
        $this->asAdmin()->postJson("/api/v1/admin/shops/{$tenant}/beta", ['plan' => 'pro', 'months' => 13])->assertUnprocessable();

        $data = $this->postJson("/api/v1/admin/shops/{$tenant}/beta", ['plan' => 'pro', 'months' => 3, 'note' => 'Beta'])->assertOk()->json('data');
        $this->assertSame('active', $data['subscription']['status']);
        $this->assertTrue($data['subscription']['beta']);
        $this->assertSame(0, $data['subscription']['monthly_value'], 'a beta shop is not revenue');

        $invoice = $data['invoices'][0];
        $this->assertSame(['beta', 'Beta مجانية', 0, 0, 3], [$invoice['method'], $invoice['method_label'], $invoice['total'], $invoice['vat'], $invoice['months']]);
        $this->assertSame(['باقة «برو» — 3 شهور', 'فترة Beta مجانية'], array_column($invoice['lines'], 'description'));
        $this->assertSame(0, array_sum(array_column($invoice['lines'], 'amount')));
        // Starts after the trial that was still running: nobody loses days.
        $this->assertEqualsWithDelta(now()->addDays(14)->addMonthsNoOverflow(3)->timestamp, strtotime($data['subscription']['paid_until']), 5);

        // The plan's modules are granted.
        app(ModuleAccess::class)->forget($tenant);
        $this->assertTrue(app(ModuleAccess::class)->enabled('repairs', $tenant));
        $this->assertSame(0, $this->getJson('/api/v1/admin/overview')->json('data.mrr'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/overview')->json('data.beta'));
        $this->assertCount(1, $this->getJson('/api/v1/admin/shops?status=beta')->json('data'));
        $this->assertSame(['beta_granted'], $this->logged());
        $this->assertTrue(app(CurrentTenant::class)->runAs($tenant, fn () => AuditEntry::query()->where('action', 'billing.beta_granted')->exists()));

        // The owner sees it on the billing page.
        Sanctum::actingAs($shopOwner);
        $this->getJson('/api/v1/billing')->assertOk()->assertJsonPath('data.subscription.beta', true);
    }
}
