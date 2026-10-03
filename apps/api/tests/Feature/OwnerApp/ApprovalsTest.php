<?php

namespace Tests\Feature\OwnerApp;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Notifications\Models\Notification;
use App\Support\Audit\AuditEntry;
use App\Support\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «اطلب موافقة»: cashiers past the owner's limits ask; the owner answers in the app, or a manager types a PIN. */
class ApprovalsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private User $cashier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->postJson('/api/v1/modules/owner_app/trial')->assertOk();
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/features/owner_app.approve_discount', ['enabled' => true, 'value' => 10])->assertOk();
        // This shop's cashiers may discount and take returns (within the owner's limits).
        $role = $this->inShop(fn () => Role::query()->where('key', 'cashier')->first());
        $this->putJson("/api/v1/roles/{$role->id}", ['name' => $role->name, 'permissions' => [...$role->permissions, 'sales.discount', 'sales.refund']])->assertOk();
        app(PermissionResolver::class)->forget();
        $this->cashier = $this->staff('cashier');
        $this->manager = $this->staff('manager');
        Sanctum::actingAs($this->cashier);
        $this->openShift(100000);
    }

    /** The charger (450 ج) with a 90 ج invoice discount (20%). */
    private function discountedSale(string $id, array $headers = [])
    {
        return $this->withHeaders($headers)->postJson('/api/v1/sales', [
            'id' => $id,
            'items' => [['variant_id' => $this->v[1], 'qty' => 1]],
            'discount' => 9000,
            'payments' => [['method' => 'cash', 'amount' => 36000]],
        ]);
    }

    public function test_a_big_discount_waits_for_the_owner_and_the_ok_is_used_once(): void
    {
        $id = (string) Str::uuid7();
        $refused = $this->discountedSale($id)->assertStatus(409)->assertJsonPath('code', 'approval_required')->json('approval');
        $this->assertSame(['discount', 36000], [$refused['kind'], $refused['amount']], 'the invoice total, for the approver');
        $this->assertStringContainsString('خصم 20%', $refused['summary']);

        $asked = $this->postJson('/api/v1/approvals', ['token' => $refused['token']])->assertCreated()->json('data');
        $this->assertSame('pending', $asked['status']);
        $this->postJson('/api/v1/approvals', ['token' => $refused['token']])->assertOk()->assertJsonPath('data.id', $asked['id']);
        $this->assertTrue(Notification::withoutTenancy()->where('type', 'approval.requested')->where('permission', 'owner_app.approve')->exists());

        // The cashier can't approve their own request.
        $this->postJson("/api/v1/approvals/{$asked['id']}/approve")->assertForbidden();
        // Not approved yet: still refused.
        $this->discountedSale($id, ['X-Approval-Id' => $asked['id']])->assertStatus(409);

        Sanctum::actingAs($this->owner);
        $this->assertCount(1, $this->getJson('/api/v1/approvals')->assertOk()->json('data'));
        $this->postJson("/api/v1/approvals/{$asked['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/approvals/{$asked['id']}/deny")->assertStatus(409)->assertJsonPath('code', 'approval_closed');
        $this->assertTrue(AuditEntry::query()->where('action', 'approvals.approved')->exists());

        Sanctum::actingAs($this->cashier);
        $this->assertSame('approved', $this->getJson("/api/v1/approvals/{$asked['id']}")->assertOk()->json('data.status'));
        // A different sale can't use it.
        $this->discountedSale((string) Str::uuid7(), ['X-Approval-Id' => $asked['id']])->assertStatus(409);
        $this->discountedSale($id, ['X-Approval-Id' => $asked['id']])->assertCreated();
        // Used up: the next discount asks again.
        $this->discountedSale((string) Str::uuid7(), ['X-Approval-Id' => $asked['id']])->assertStatus(409);
    }

    public function test_a_manager_approves_at_the_counter_with_a_pin(): void
    {
        Sanctum::actingAs($this->manager);
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '1234'])->assertUnprocessable()->assertJsonPath('code', 'pin_weak');
        $this->putJson('/api/v1/account/pin', ['password' => 'nope', 'pin' => '4826'])->assertUnprocessable()->assertJsonPath('code', 'password_incorrect');
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '4826'])->assertOk()->assertJsonPath('data.has_pin', true);
        // A manager needs no approval for their own discount.
        $this->openShift(0);
        $this->discountedSale((string) Str::uuid7())->assertCreated();

        Sanctum::actingAs($this->cashier);
        $id = (string) Str::uuid7();
        $token = $this->discountedSale($id)->assertStatus(409)->json('approval.token');
        $this->postJson('/api/v1/approvals/pin', ['token' => $token, 'pin' => '9999'])->assertUnprocessable()->assertJsonPath('code', 'pin_incorrect');
        $ok = $this->postJson('/api/v1/approvals/pin', ['token' => $token, 'pin' => '4826'])->assertCreated()->json('data');
        $this->assertSame(['approved', 'pin', 'manager'], [$ok['status'], $ok['via'], $ok['decided_by_name']]);
        $this->discountedSale($id, ['X-Approval-Id' => $ok['id']])->assertCreated();
        $this->postJson('/api/v1/approvals', ['token' => 'forged'])->assertUnprocessable()->assertJsonPath('code', 'approval_token_invalid');
    }

    public function test_withdrawals_and_returns_past_their_limits(): void
    {
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/features/owner_app.approve_withdrawal', ['enabled' => true, 'value' => 200])->assertOk();
        $this->putJson('/api/v1/features/owner_app.approve_return', ['enabled' => true, 'value' => 100])->assertOk();

        Sanctum::actingAs($this->cashier);
        $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 20000, 'category' => 'other', 'note' => 'كهربا'])->assertCreated(); // exactly the limit
        $refused = $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 30000, 'category' => 'other', 'note' => 'كهربا'])->assertStatus(409)->json('approval');
        $this->assertSame('withdrawal', $refused['kind']);

        $sale = $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated()->json('data');
        $return = fn (array $h = []) => $this->withHeaders($h)->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]]);
        $token = $return()->assertStatus(409)->assertJsonPath('approval.kind', 'return')->json('approval.token');
        $asked = $this->postJson('/api/v1/approvals', ['token' => $token])->assertCreated()->json('data');

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/approvals/{$asked['id']}/deny", ['reason' => 'هات الفاتورة الأول'])->assertOk()->assertJsonPath('data.status', 'denied');

        Sanctum::actingAs($this->cashier);
        $return(['X-Approval-Id' => $asked['id']])->assertStatus(409);
        $this->assertSame('هات الفاتورة الأول', $this->getJson("/api/v1/approvals/{$asked['id']}")->json('data.reason'));
    }

    public function test_selling_below_cost_can_need_an_ok_instead_of_a_warning(): void
    {
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/features/owner_app.approve_discount', ['enabled' => false])->assertOk();
        $this->putJson('/api/v1/features/owner_app.approve_below_cost', ['enabled' => true])->assertOk();
        $this->putJson('/api/v1/features/sales.below_cost', ['enabled' => true, 'value' => 'block'])->assertOk();

        Sanctum::actingAs($this->cashier);
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated(); // not below cost
        $id = (string) Str::uuid7();
        // 300 ج cost, sold for 250 ج after a 200 ج discount.
        $sale = fn (array $h = []) => $this->withHeaders($h)->postJson('/api/v1/sales', ['id' => $id, 'items' => [['variant_id' => $this->v[1], 'qty' => 1]], 'discount' => 20000, 'payments' => [['method' => 'cash', 'amount' => 25000]]]);
        $token = $sale()->assertStatus(409)->assertJsonPath('approval.kind', 'below_cost')->json('approval.token');

        Sanctum::actingAs($this->manager);
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '7391'])->assertOk();
        Sanctum::actingAs($this->cashier);
        $ok = $this->postJson('/api/v1/approvals/pin', ['token' => $token, 'pin' => '7391'])->assertCreated()->json('data');
        $sale(['X-Approval-Id' => $ok['id']])->assertCreated();
    }

    public function test_one_ok_covers_a_big_discount_that_also_sells_below_cost(): void
    {
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/features/owner_app.approve_below_cost', ['enabled' => true])->assertOk();

        Sanctum::actingAs($this->cashier);
        $id = (string) Str::uuid7();
        // 200 ج off a 450 ج charger that cost 300 ج: 44% and below cost.
        $sale = fn (array $h = []) => $this->withHeaders($h)->postJson('/api/v1/sales', ['id' => $id, 'items' => [['variant_id' => $this->v[1], 'qty' => 1]], 'discount' => 20000, 'payments' => [['method' => 'cash', 'amount' => 25000]]]);
        $approval = $sale()->assertStatus(409)->json('approval');
        $this->assertStringContainsString('خصم 44%', $approval['summary']);
        $this->assertStringContainsString('بأقل من تكلفته', $approval['summary']);
        $asked = $this->postJson('/api/v1/approvals', ['token' => $approval['token']])->assertCreated()->json('data');

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/approvals/{$asked['id']}/approve")->assertOk();
        Sanctum::actingAs($this->cashier);
        $sale(['X-Approval-Id' => $asked['id']])->assertCreated();
    }

    public function test_credit_past_the_customer_limit_can_be_approved_instead_of_refused(): void
    {
        Sanctum::actingAs($this->owner);
        $customer = $this->postJson('/api/v1/customers', ['name' => 'كريم', 'phone' => '01012345678', 'credit_limit' => 20000])->assertCreated()->json('data');
        $role = $this->inShop(fn () => Role::query()->where('key', 'cashier')->first());
        $this->putJson("/api/v1/roles/{$role->id}", ['name' => $role->name, 'permissions' => [...$role->permissions, 'customers.credit']])->assertOk();
        app(PermissionResolver::class)->forget();
        $sale = fn (string $id, array $headers = []) => $this->withHeaders($headers)->postJson('/api/v1/sales', [
            'id' => $id,
            'items' => [['variant_id' => $this->v[1], 'qty' => 1]],
            'customer_id' => $customer['id'],
            'payments' => [['method' => 'cash', 'amount' => 15000], ['method' => 'credit', 'amount' => 30000]],
        ]);

        // Switch off: past the limit is refused as before.
        Sanctum::actingAs($this->cashier);
        $sale((string) Str::uuid7())->assertUnprocessable()->assertJsonPath('code', 'credit_limit_exceeded');

        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/features/owner_app.approve_credit_limit', ['enabled' => true])->assertOk();
        Sanctum::actingAs($this->cashier);
        $id = (string) Str::uuid7();
        $refused = $sale($id)->assertStatus(409)->assertJsonPath('code', 'approval_required')->json('approval');
        $this->assertSame('credit_limit', $refused['kind']);
        $this->assertStringContainsString('فوق حده بـ 100.00 ج', $refused['summary']);

        Sanctum::actingAs($this->manager);
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '4826'])->assertOk();
        Sanctum::actingAs($this->cashier);
        $ok = $this->postJson('/api/v1/approvals/pin', ['token' => $refused['token'], 'pin' => '4826'])->assertCreated()->json('data');
        $sale($id, ['X-Approval-Id' => $ok['id']])->assertCreated();
        Sanctum::actingAs($this->owner);
        $this->assertSame(30000, $this->getJson("/api/v1/customers/{$customer['id']}")->json('data.balance'));
    }

    public function test_the_owner_can_require_two_factor_and_a_fresh_fingerprint_for_big_approvals(): void
    {
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/features/owner_app.approve_two_factor', ['enabled' => true])->assertOk();
        $this->putJson('/api/v1/features/owner_app.approve_step_up', ['enabled' => true, 'value' => 300])->assertOk();
        Sanctum::actingAs($this->manager);
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '4826'])->assertOk();

        Sanctum::actingAs($this->cashier);
        $id = (string) Str::uuid7();
        $token = $this->discountedSale($id)->assertStatus(409)->json('approval.token');
        // The manager's PIN alone isn't enough without two-factor sign-in on their account.
        $this->postJson('/api/v1/approvals/pin', ['token' => $token, 'pin' => '4826'])->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $asked = $this->postJson('/api/v1/approvals', ['token' => $token])->assertCreated()->json('data');

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/approvals/{$asked['id']}/approve")->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $secret = $this->postJson('/api/v1/account/two-factor/setup', ['password' => 'password'])->assertOk()->json('data.secret');
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => Totp::code($secret)])->assertOk();
        // 360 ج > 300 ج: confirm it's really the owner first.
        $this->postJson("/api/v1/approvals/{$asked['id']}/approve")->assertForbidden()->assertJsonPath('code', 'step_up_required');
        // The PIN (or a passkey) on this device confirms it for a few minutes.
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '7391'])->assertOk();
        $this->postJson('/api/v1/account/unlock', ['pin' => '7391'])->assertOk();
        $this->postJson("/api/v1/approvals/{$asked['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }
}
