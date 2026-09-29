<?php

namespace Tests\Feature\ShopOrders;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Support\Events\StoredEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class ShopOrdersTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $buyer;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->registerShop(ShopType::Repair);
        $this->seller = $this->registerShop(ShopType::Wholesale);
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function codeOf(User $user): string
    {
        return $user->tenant()->firstOrFail()->code;
    }

    private function connect(): void
    {
        $id = $this->as($this->buyer)->postJson('/api/v1/shop-connections', ['code' => $this->codeOf($this->seller)])
            ->assertCreated()->json('data.id');
        $this->as($this->seller)->postJson("/api/v1/shop-connections/{$id}/accept")->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function placeOrder(array $overrides = []): array
    {
        return $this->as($this->buyer)->postJson('/api/v1/shop-orders', [
            'seller_tenant_id' => $this->seller->tenant_id,
            'type' => 'goods',
            'items' => [
                ['description' => 'شاشة iPhone 11 أصلي سيرفس', 'quantity' => 2],
                ['description' => 'سوكيت شحن Oppo A57', 'quantity' => 5],
            ],
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    private function move(User $user, string $orderId, string $status, array $extra = [])
    {
        return $this->as($user)->postJson("/api/v1/shop-orders/{$orderId}/transition", ['status' => $status, ...$extra]);
    }

    public function test_shops_become_partners_by_code(): void
    {
        $response = $this->as($this->buyer)->postJson('/api/v1/shop-connections', ['code' => strtolower($this->codeOf($this->seller))]);
        $response->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.direction', 'outgoing');

        $incoming = $this->as($this->seller)->getJson('/api/v1/shop-connections')->assertOk()->json('data.0');
        $this->assertSame('incoming', $incoming['direction']);
        $this->assertSame($this->buyer->tenant()->firstOrFail()->name, $incoming['shop']['name']);

        $this->postJson("/api/v1/shop-connections/{$incoming['id']}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
    }

    public function test_asking_a_shop_that_already_asked_you_makes_you_partners(): void
    {
        $this->as($this->buyer)->postJson('/api/v1/shop-connections', ['code' => $this->codeOf($this->seller)])->assertCreated();

        $this->as($this->seller)->postJson('/api/v1/shop-connections', ['code' => $this->codeOf($this->buyer)])
            ->assertCreated()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_connection_requests_are_validated(): void
    {
        $this->as($this->buyer)->postJson('/api/v1/shop-connections', ['code' => 'ZZZZZZ'])->assertNotFound()->assertJson(['code' => 'shop_not_found']);
        $this->postJson('/api/v1/shop-connections', ['code' => $this->codeOf($this->buyer)])->assertUnprocessable()->assertJson(['code' => 'cannot_connect_to_self']);

        $accessories = $this->shopWithoutOrders();
        $this->as($this->buyer)->postJson('/api/v1/shop-connections', ['code' => $this->codeOf($accessories)])
            ->assertUnprocessable()
            ->assertJson(['code' => 'partner_module_disabled']);
    }

    public function test_only_partners_can_order(): void
    {
        $this->as($this->buyer)->postJson('/api/v1/shop-orders', [
            'seller_tenant_id' => $this->seller->tenant_id,
            'type' => 'goods',
            'items' => [['description' => 'شاشة', 'quantity' => 1]],
        ])->assertForbidden()->assertJson(['code' => 'not_partners']);
    }

    public function test_full_order_lifecycle_between_two_shops(): void
    {
        $this->connect();
        $order = $this->placeOrder(['notes' => 'محتاجها بكرة الصبح']);

        $this->assertSame('placed', $order['status']);
        $this->assertSame('buyer', $order['my_party']);
        $this->assertMatchesRegularExpression('/^SO-\d{6}$/', $order['reference']);

        // The seller sees it in its inbox and can accept or reject.
        $inbox = $this->as($this->seller)->getJson('/api/v1/shop-orders?box=incoming')->assertOk()->json('data');
        $this->assertCount(1, $inbox);
        $this->assertSame('seller', $inbox[0]['my_party']);
        $this->assertEqualsCanonicalizing(['accepted', 'rejected'], array_column($inbox[0]['allowed_transitions'], 'status'));

        [$screen, $socket] = array_column($order['items'], 'id');
        $accepted = $this->move($this->seller, $order['id'], 'accepted', ['prices' => [$screen => 285000, $socket => 9000]])
            ->assertOk()->json('data');
        $this->assertSame(2 * 285000 + 5 * 9000, $accepted['total']);

        $this->move($this->seller, $order['id'], 'preparing')->assertOk();
        $this->move($this->seller, $order['id'], 'ready')->assertOk();
        $this->move($this->seller, $order['id'], 'delivered', ['note' => 'مع المندوب'])->assertOk();

        $done = $this->move($this->buyer, $order['id'], 'completed')->assertOk()->json('data');
        $this->assertSame('completed', $done['status']);
        $this->assertSame([], $done['allowed_transitions']);
        $this->assertSame(['placed', 'accepted', 'preparing', 'ready', 'delivered', 'completed'], array_column($done['activities'], 'to_status'));

        // Each shop gets its own event for every step (for its notifications / books).
        $events = StoredEvent::query()->where('name', ShopOrderUpdated::NAME)->get();
        $this->assertSame(12, $events->count());
        $this->assertSame(6, $events->where('tenant_id', $this->seller->tenant_id)->count());
    }

    public function test_each_party_can_only_make_its_own_moves(): void
    {
        $this->connect();
        $order = $this->placeOrder();

        $this->move($this->buyer, $order['id'], 'accepted')->assertUnprocessable()->assertJson(['code' => 'invalid_transition']);
        $this->move($this->seller, $order['id'], 'completed')->assertUnprocessable();
        $this->move($this->seller, $order['id'], 'accepted')->assertOk();
        $this->move($this->buyer, $order['id'], 'accepted', ['prices' => []])->assertUnprocessable();

        // Only the seller prices, and only when accepting.
        $this->move($this->buyer, $order['id'], 'cancelled', ['prices' => [$order['items'][0]['id'] => 1]])
            ->assertUnprocessable()->assertJson(['code' => 'prices_not_allowed']);
        $this->move($this->buyer, $order['id'], 'cancelled', ['note' => 'لقيتها في مكان تاني'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_repair_job_can_be_sent_to_a_partner_shop(): void
    {
        $this->connect();

        $order = $this->placeOrder([
            'type' => 'repair',
            'items' => [['description' => 'مش بيفتح - قفلة بوردة', 'quantity' => 1, 'device_model' => 'iPhone 12', 'imei' => '356984101234567']],
        ]);

        $this->assertSame('شغل صيانة', $order['type_label']);
        $this->assertSame('iPhone 12', $order['items'][0]['device_model']);
    }

    public function test_orders_are_private_to_the_two_shops(): void
    {
        $this->connect();
        $order = $this->placeOrder();
        $stranger = $this->registerShop(ShopType::Wholesale);

        $this->as($stranger)->getJson("/api/v1/shop-orders/{$order['id']}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/shop-orders?box=incoming')->json('data'));
        $this->move($stranger, $order['id'], 'accepted')->assertNotFound();
    }

    public function test_the_module_must_be_enabled(): void
    {
        $accessories = $this->shopWithoutOrders();

        $this->as($accessories)->getJson('/api/v1/shop-orders')->assertForbidden()->assertJson(['code' => 'module_not_enabled']);
    }

    /** A shop that hid the inter-shop orders module (every shop type gets it on trial). */
    private function shopWithoutOrders(): User
    {
        $shop = $this->registerShop(ShopType::Accessories);
        $this->as($shop)->postJson('/api/v1/modules/shop_orders/disable')->assertOk();

        return $shop;
    }
}
