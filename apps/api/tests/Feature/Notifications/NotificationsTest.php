<?php

namespace Tests\Feature\Notifications;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $buyer;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->registerShop(ShopType::Repair);
        $this->seller = $this->registerShop(ShopType::Wholesale);
        $this->relay();
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

    private function bell(User $user): array
    {
        return $this->as($user)->getJson('/api/v1/notifications')->assertOk()->json();
    }

    private function unread(User $user): int
    {
        return $this->as($user)->getJson('/api/v1/notifications/unread')->assertOk()->json('data.count');
    }

    private function connect(): void
    {
        $code = $this->seller->tenant()->firstOrFail()->code;
        $id = $this->as($this->buyer)->postJson('/api/v1/shop-connections', ['code' => $code])->assertCreated()->json('data.id');
        $this->relay();
        $this->as($this->seller)->postJson("/api/v1/shop-connections/{$id}/accept")->assertOk();
    }

    private function placeOrder(): array
    {
        $order = $this->as($this->buyer)->postJson('/api/v1/shop-orders', [
            'seller_tenant_id' => $this->seller->tenant_id,
            'type' => 'goods',
            'items' => [['description' => 'شاشة iPhone 11', 'quantity' => 2]],
        ])->assertCreated()->json('data');
        $this->relay();

        return $order;
    }

    private function staff(User $owner, array $permissions): User
    {
        $this->as($owner);
        $roleId = $this->postJson('/api/v1/roles', ['name' => 'دور '.implode(' ', $permissions), 'permissions' => $permissions])->assertCreated()->json('data.id');
        $branchId = app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => Branch::query()->value('id'));
        $id = $this->postJson('/api/v1/users', [
            'name' => 'موظف', 'phone' => '011'.random_int(10000000, 99999999), 'password' => 'password', 'role_id' => $roleId, 'branch_ids' => [$branchId],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }

    public function test_the_other_shop_is_told_not_the_one_that_acted(): void
    {
        $this->connect();
        $this->assertSame(['طلب شراكة من «محل 1»'], array_column($this->bell($this->seller)['data'], 'title'));
        $this->assertSame('/shop-orders/partners', $this->bell($this->seller)['data'][0]['to']);
        $this->assertSame([], $this->bell($this->buyer)['data'], 'asking is not news to the one who asked');

        $order = $this->placeOrder();
        [$new] = $this->bell($this->seller)['data'];
        $this->assertSame(['طلب جديد من «محل 1»', $order['reference'], "/shop-orders/{$order['id']}", false], [$new['title'], $new['body'], $new['to'], $new['read']]);
        $this->assertSame(0, $this->unread($this->buyer));

        $item = $order['items'][0]['id'];
        $this->as($this->seller)->postJson("/api/v1/shop-orders/{$order['id']}/transition", ['status' => 'accepted', 'prices' => [$item => 150000]])->assertOk();
        $this->relay();
        [$accepted] = $this->bell($this->buyer)['data'];
        $this->assertSame(["«محل 2» قبل الطلب {$order['reference']}", 'الحساب 3,000.00 ج', "/shop-orders/{$order['id']}"], [$accepted['title'], $accepted['body'], $accepted['to']]);
        $this->assertSame(2, $this->unread($this->seller), 'its own acceptance adds nothing');

        // A repair job sent to the partner reads as such.
        $this->as($this->buyer)->postJson('/api/v1/shop-orders', [
            'seller_tenant_id' => $this->seller->tenant_id, 'type' => 'repair', 'items' => [['description' => 'iPhone 11 بوردة', 'quantity' => 1]],
        ])->assertCreated();
        $this->relay();
        $this->assertSame('شغل صيانة جديد من «محل 1»', $this->bell($this->seller)['data'][0]['title']);
    }

    public function test_users_see_only_what_their_permissions_allow(): void
    {
        $this->connect();
        $this->placeOrder();

        $orders = $this->staff($this->seller, ['shop_orders.view']);
        $cashier = $this->staff($this->seller, ['sales.sell']);

        $this->assertSame(['طلب جديد من «محل 1»'], array_column($this->bell($orders)['data'], 'title'));
        $this->assertSame(1, $this->unread($orders));
        $this->assertSame([], $this->bell($cashier)['data']);
        $this->assertSame(0, $this->unread($cashier));
        $this->assertSame(2, $this->unread($this->seller), 'the owner sees both');

        // Can't mark what one can't see; nor another shop's.
        $partnership = collect($this->bell($this->seller)['data'])->firstWhere('to', '/shop-orders/partners');
        $this->as($orders)->postJson("/api/v1/notifications/{$partnership['id']}/read")->assertNotFound();
        $this->as($this->buyer)->postJson("/api/v1/notifications/{$partnership['id']}/read")->assertNotFound();
        $this->assertSame(2, $this->unread($this->seller));
    }

    public function test_read_state_is_per_user_and_unread_come_first(): void
    {
        $this->connect();
        $this->placeOrder();
        $orders = $this->staff($this->seller, ['shop_orders.view', 'shop_orders.partners']);

        $bell = $this->bell($this->seller);
        $this->assertSame([2, 2], [$bell['meta']['total'], $bell['meta']['unread']]);
        [$newest, $older] = $bell['data'];
        $this->assertSame('طلب جديد من «محل 1»', $newest['title'], 'newest first');

        $this->as($this->seller)->postJson("/api/v1/notifications/{$newest['id']}/read")->assertOk()->assertJsonPath('data.count', 1);
        $bell = $this->bell($this->seller);
        $this->assertSame([[$older['id'], false], [$newest['id'], true]], array_map(fn ($n) => [$n['id'], $n['read']], $bell['data']), 'unread first');
        $this->assertSame(2, $this->unread($orders), 'someone else\'s reading changes nothing for you');

        $this->as($orders)->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame([0, 1], [$this->unread($orders), $this->unread($this->seller)]);
        $this->as($this->seller)->postJson("/api/v1/notifications/{$newest['id']}/read")->assertOk()->assertJsonPath('data.count', 1);
    }
}
