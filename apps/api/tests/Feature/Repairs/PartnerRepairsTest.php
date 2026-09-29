<?php

namespace Tests\Feature\Repairs;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/**
 * An accessories shop takes a phone in from a customer and sends it to a partner repair shop;
 * the partner works it as its own ticket and both sides follow along.
 */
class PartnerRepairsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $shop;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->registerShop(ShopType::Accessories);
        $this->partner = $this->registerShop(ShopType::Repair);
        $this->relay();

        // An accessories shop sees repairs but tries it on its own.
        $this->as($this->shop)->postJson('/api/v1/modules/repairs/trial')->assertOk();
        $this->relay();

        $code = $this->partner->tenant()->firstOrFail()->code;
        $id = $this->as($this->shop)->postJson('/api/v1/shop-connections', ['code' => $code])->assertCreated()->json('data.id');
        $this->as($this->partner)->postJson("/api/v1/shop-connections/{$id}/accept")->assertOk();
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function relay(): void
    {
        app(EventRelay::class)->publishPending();
    }

    private function ticket(User $user, string $id): array
    {
        return $this->as($user)->getJson("/api/v1/repairs/tickets/{$id}")->assertOk()->json('data');
    }

    private function receive(): array
    {
        return $this->as($this->shop)->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'نادر', 'customer_phone' => '01234567890',
            'device_name' => 'iPhone 11', 'imei' => '356789012345678', 'reported_note' => 'مش بيشحن',
        ])->assertCreated()->json('data');
    }

    public function test_a_device_goes_to_a_partner_and_comes_back_with_its_price(): void
    {
        $mine = $this->receive();
        $sent = $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/outsource", [
            'partner_tenant_id' => $this->partner->tenant_id, 'note' => 'غالباً السوكيت',
        ])->assertOk()->json('data.outsourced');
        $this->assertSame(['محل 2', 'placed', true], [$sent['shop'], $sent['status'], $sent['active']]);
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/outsource", ['partner_tenant_id' => $this->partner->tenant_id])
            ->assertStatus(422)->assertJsonPath('code', 'already_outsourced');

        // The partner accepts: a ticket opens on its side, the sending shop as the customer.
        $order = $this->as($this->partner)->getJson("/api/v1/shop-orders/{$sent['order_id']}")->assertOk()->json('data');
        $this->assertSame(['repair', 'iPhone 11', '356789012345678'], [$order['type'], $order['items'][0]['device_model'], $order['items'][0]['imei']]);
        $this->as($this->partner)->postJson("/api/v1/shop-orders/{$sent['order_id']}/transition", ['status' => 'accepted'])->assertOk();
        $this->relay();

        $theirs = $this->as($this->partner)->getJson('/api/v1/repairs/tickets')->json('data');
        $this->assertCount(1, $theirs);
        $this->assertSame(['محل 1', 'iPhone 11', $order['reference']], [$theirs[0]['customer_name'], $theirs[0]['device_name'], $theirs[0]['partner']['reference']]);
        $this->assertStringContainsString('غالباً السوكيت', $theirs[0]['reported_note']);
        $url = "/api/v1/repairs/tickets/{$theirs[0]['id']}";

        // Working on it moves the order along.
        $this->as($this->partner)->postJson("{$url}/status", ['status' => 'diagnosing'])->assertOk();
        $this->as($this->partner)->patchJson($url, ['labor' => 40000])->assertOk();
        $this->as($this->partner)->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->as($this->partner)->postJson("{$url}/status", ['status' => 'ready'])->assertOk();
        $this->assertSame('ready', $this->as($this->partner)->getJson("/api/v1/shop-orders/{$sent['order_id']}")->json('data.status'));
        $this->relay();
        $this->assertSame('ready', $this->ticket($this->shop, $mine['id'])['outsourced']['status']);

        // The shop can't hand the phone to its customer while the partner still has it.
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/status", ['status' => 'diagnosing'])->assertOk();
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/status", ['status' => 'repairing'])->assertOk();
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/status", ['status' => 'ready'])->assertOk();
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/deliver", [])->assertStatus(422)->assertJsonPath('code', 'ticket_outsourced');

        // The partner hands it back on account: the order is delivered at the ticket's price.
        $this->as($this->partner)->postJson("{$url}/deliver", ['payments' => [['method' => 'credit', 'amount' => 40000]]])->assertOk();
        $back = $this->as($this->partner)->getJson("/api/v1/shop-orders/{$sent['order_id']}")->json('data');
        $this->assertSame(['delivered', 40000], [$back['status'], $back['total']]);
        $this->relay();

        $mine = $this->ticket($this->shop, $mine['id']);
        $this->assertSame(['delivered', 40000], [$mine['outsourced']['status'], $mine['outsourced']['cost']]);
        $this->assertContains('partner', array_column($mine['events'], 'type'));

        // Now the shop charges its customer; the partner's price is its cost.
        $this->as($this->shop)->patchJson("/api/v1/repairs/tickets/{$mine['id']}", ['labor' => 60000])->assertOk();
        $this->as($this->shop)->postJson('/api/v1/cash/shifts', ['opening_cash' => 0])->assertCreated();
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/deliver", ['payments' => [['method' => 'cash', 'amount' => 60000]]])->assertOk();

        $report = $this->as($this->shop)->getJson('/api/v1/reports/repairs?'.http_build_query(['from' => now('Africa/Cairo')->toDateString(), 'to' => now('Africa/Cairo')->toDateString()]))->assertOk()->json('data');
        $profit = collect($report['summary'])->firstWhere('label', 'المكسب (بعد القطع)');
        $this->assertSame(20000, $profit['value']);
    }

    public function test_who_may_send_and_what(): void
    {
        $mine = $this->receive();

        // Not a partner.
        $stranger = $this->registerShop(ShopType::Repair);
        $this->as($this->shop)->postJson("/api/v1/repairs/tickets/{$mine['id']}/outsource", ['partner_tenant_id' => $stranger->tenant_id])
            ->assertForbidden()->assertJsonPath('code', 'not_partners');

        // A cashier takes devices in but doesn't send them out.
        $cashierRole = $this->as($this->shop)->getJson('/api/v1/roles')->json('data');
        $roleId = collect($cashierRole)->firstWhere('key', 'cashier')['id'];
        $branchId = $mine['branch_id'] ?? null;
        $userId = $this->postJson('/api/v1/users', ['name' => 'كاشير', 'phone' => '01122223333', 'password' => 'password', 'role_id' => $roleId, 'branch_ids' => array_filter([$branchId])])
            ->assertCreated()->json('data.id');
        $this->as(User::query()->findOrFail($userId))->postJson("/api/v1/repairs/tickets/{$mine['id']}/outsource", ['partner_tenant_id' => $this->partner->tenant_id])
            ->assertForbidden();
    }
}
