<?php

namespace Tests\Feature\OnlineStore;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Notifications\Models\Notification;
use App\Support\Events\EventRelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Orders on the online store: placed by customers, moved by the shop, invoiced at the POS. */
class OnlineOrdersTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private string $zone;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.store.host' => '', 'services.store.url' => 'https://store.muhasebi.com']);
        Storage::fake('local');
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/online-store/settings', [
            'slug' => 'elnour', 'mode' => 'orders', 'delivery' => true, 'pay_transfer' => true,
            'transfer_wallet' => '01011112222', 'free_delivery_over' => 100000,
        ])->assertOk();
        $this->zone = $this->postJson('/api/v1/online-store/zones', ['name' => 'مدينة نصر', 'fee' => 3000])->assertCreated()->json('data.0.id');
    }

    private function order(array $overrides = [])
    {
        return $this->postJson('/api/v1/public/stores/elnour/orders', [
            'name' => 'أحمد',
            'phone' => '01055556666',
            'fulfilment' => 'delivery',
            'zone_id' => $this->zone,
            'address' => 'شارع عباس العقاد',
            'payment' => 'cod',
            'consent' => true,
            'items' => [['variant_id' => $this->v[0], 'qty' => 2]],
            ...$overrides,
        ]);
    }

    public function test_the_store_shows_how_to_order_and_prices_come_from_the_server(): void
    {
        $home = $this->getJson('/api/v1/public/stores/elnour')->assertOk()->json('data');
        $this->assertSame(['orders', true, true, '+201011112222'], [$home['store']['mode'], $home['store']['ordering']['delivery'], $home['store']['ordering']['pay_transfer'], $home['store']['ordering']['transfer_wallet']]);
        $this->assertSame([['id' => $this->zone, 'name' => 'مدينة نصر', 'fee' => 3000]], $home['zones']);

        // Whatever the cart said, the price is the store's: 2 × 100 ج + 30 ج delivery.
        $placed = $this->order(['items' => [['variant_id' => $this->v[0], 'qty' => 2, 'price' => 1]]])->assertCreated()->json('data');
        $this->assertSame(['WEB-00001', 'new', 20000, 3000, 23000], [$placed['reference'], $placed['status'], $placed['subtotal'], $placed['delivery_fee'], $placed['total']]);
        $this->assertSame(32, strlen($placed['token']));

        // Free delivery above 1000 ج.
        $big = $this->order(['phone' => '01055556667', 'items' => [['variant_id' => $this->v[1], 'qty' => 3]]])->assertCreated()->json('data');
        $this->assertSame([135000, 0], [$big['subtotal'], $big['delivery_fee']]);

        // Tracking page.
        $track = $this->getJson("/api/v1/public/stores/elnour/orders/{$placed['token']}")->assertOk()->json('data');
        $this->assertSame(['جراب', 'جديد'], [$track['items'][0]['name'], $track['timeline'][0]['label']]);
        $this->assertArrayNotHasKey('customer_phone', $track);
        $this->getJson('/api/v1/public/stores/elnour/orders/'.str_repeat('a', 32))->assertNotFound();

        // With consent they became a customer of the shop.
        $this->assertSame('+201055556666', $this->getJson('/api/v1/customers?q=أحمد')->json('data.0.phone'));
    }

    public function test_refusals(): void
    {
        $this->order(['items' => [['variant_id' => $this->v[1], 'qty' => 21]]])->assertUnprocessable()->assertJsonPath('code', 'out_of_stock');
        $this->order(['zone_id' => null])->assertUnprocessable()->assertJsonPath('code', 'zone_required');
        $this->order(['address' => ''])->assertUnprocessable()->assertJsonPath('code', 'address_required');
        $this->order(['payment' => 'transfer'])->assertUnprocessable()->assertJsonPath('code', 'proof_required');
        $this->order(['website' => 'http://spam'])->assertUnprocessable();

        // Hidden from the store: can't be ordered.
        $productId = $this->getJson('/api/v1/products?q=جراب')->json('data.0.id');
        $this->patchJson('/api/v1/products/online', ['ids' => [$productId], 'visible' => false])->assertOk();
        $this->order()->assertUnprocessable()->assertJsonPath('code', 'items_unavailable');
        $this->patchJson('/api/v1/products/online', ['ids' => [$productId], 'visible' => true])->assertOk();

        $this->putJson('/api/v1/online-store/settings', ['min_order' => 50000])->assertOk();
        $this->order()->assertUnprocessable()->assertJsonPath('code', 'below_min_order');
        $this->putJson('/api/v1/online-store/settings', ['min_order' => 0])->assertOk();

        // Three still-new orders from one phone are enough (another IP: the store's own limit isn't the point here).
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        foreach (range(1, 3) as $i) {
            $this->order()->assertCreated();
        }
        $this->order()->assertStatus(429)->assertJsonPath('code', 'too_many_orders');

        // Too many tries from one IP.
        foreach (range(1, 20) as $i) {
            $this->order(['zone_id' => null]);
        }
        $this->order()->assertStatus(429)->assertJsonPath('code', 'too_many_requests');

        // WhatsApp-only stores don't take orders.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3']);
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'whatsapp'])->assertOk();
        $this->order(['phone' => '01099998888'])->assertStatus(409)->assertJsonPath('code', 'store_not_taking_orders');
    }

    public function test_orders_only_in_the_shops_hours(): void
    {
        // 10:00 → 02:00 Cairo time (past midnight).
        $this->putJson('/api/v1/online-store/settings', ['orders_from' => '10:00', 'orders_until' => '02:00'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 05:00', 'Africa/Cairo'));
        $this->assertFalse($this->getJson('/api/v1/public/stores/elnour')->json('data.store.ordering.open_now'));
        $this->order()->assertStatus(409)->assertJsonPath('code', 'orders_closed_now');
        $this->travelTo(CarbonImmutable::parse('2026-10-05 01:30', 'Africa/Cairo'));
        $this->order()->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00', 'Africa/Cairo'));
        $this->assertSame(['from' => '10:00', 'until' => '02:00'], $this->getJson('/api/v1/public/stores/elnour')->json('data.store.ordering.hours'));
        $this->order(['phone' => '01055556668'])->assertCreated();

        $this->putJson('/api/v1/online-store/settings', ['orders_from' => '10:00'])->assertUnprocessable();
        $this->putJson('/api/v1/online-store/settings', ['orders_from' => null, 'orders_until' => null])->assertOk();
        $this->assertNull($this->getJson('/api/v1/public/stores/elnour')->json('data.store.ordering.hours'));
    }

    public function test_transfer_proof_is_private_and_the_same_checkout_is_saved_once(): void
    {
        $id = '0199aaaa-0000-7000-8000-000000000001';
        $placed = $this->order(['id' => $id, 'payment' => 'transfer', 'proof' => UploadedFile::fake()->image('t.jpg', 600, 900)])->assertCreated()->json('data');
        $this->order(['id' => $id, 'payment' => 'transfer', 'proof' => UploadedFile::fake()->image('t.jpg')])->assertCreated();
        $this->assertSame(1, $this->getJson('/api/v1/online-store/orders')->json('meta.total'));

        $order = $this->getJson("/api/v1/online-store/orders/{$id}")->assertOk()->json('data');
        $this->assertTrue($order['has_proof']);
        $this->assertStringEndsWith("/elnour/o/{$placed['token']}", $order['track_url']);
        $this->get("/api/v1/online-store/orders/{$id}/proof")->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_the_shop_moves_it_and_the_pos_invoice_closes_it(): void
    {
        $order = $this->order()->assertCreated()->json('data');
        $id = $this->getJson('/api/v1/online-store/orders')->json('data.0.id');
        app(EventRelay::class)->publishPending();
        $this->assertSame('طلب أونلاين جديد WEB-00001', Notification::withoutTenancy()->latest('created_at')->value('title'));
        $this->assertSame(['new' => 1, 'open' => 1], $this->getJson('/api/v1/online-store/orders/summary')->json('data'));

        $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'preparing'])->assertUnprocessable()->assertJsonPath('code', 'online_order_status_invalid');
        $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'confirmed'])->assertOk();
        $moved = $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'preparing'])->assertOk()->json('data');
        $this->assertSame(['out_for_delivery', 'cancelled'], array_column($moved['next'], 'value'));
        $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'delivered'])->assertUnprocessable();

        // The price changed since: the invoice keeps the order's price; the fee goes into the drawer.
        $productId = $this->getJson('/api/v1/products?q=جراب')->json('data.0.id');
        $variant = $this->getJson("/api/v1/products/{$productId}")->json('data.variants.0');
        $this->patchJson("/api/v1/products/{$productId}", ['variants' => [[...$variant, 'price_retail' => 12000]]])->assertOk();
        $this->withHeaders(['X-Branch-Id' => $this->branchId]);
        $this->openShift();
        $sale = $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[0], 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20000]],
            'online_order_id' => $id,
            'delivery_fee_collected' => true,
        ])->assertCreated()->json('data');
        $this->assertSame([20000, ['type' => 'online_order', 'id' => $id]], [$sale['total'], $sale['origin']]);
        $this->assertStringContainsString('WEB-00001', $sale['notes']);

        $closed = $this->getJson("/api/v1/online-store/orders/{$id}")->json('data');
        $this->assertSame(['delivered', $sale['reference'], true], [$closed['status'], $closed['sale_reference'], $closed['fee_collected']]);
        $this->assertSame(23000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'));
        $this->assertSame('اتسلّم', $this->getJson("/api/v1/public/stores/elnour/orders/{$order['token']}")->json('data.status_label'));

        // Invoiced once.
        $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[0], 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
            'online_order_id' => $id,
        ])->assertUnprocessable()->assertJsonPath('code', 'online_order_invoiced');
    }

    public function test_cancel_needs_a_reason_and_orders_are_kept_apart(): void
    {
        $this->order()->assertCreated();
        $id = $this->getJson('/api/v1/online-store/orders')->json('data.0.id');
        $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'cancelled'])->assertUnprocessable()->assertJsonPath('code', 'cancel_reason_required');
        $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'cancelled', 'reason' => 'مش متوفر'])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $other = $this->registerShop(ShopType::Accessories);
        Sanctum::actingAs($other);
        app(PermissionResolver::class)->forget();
        $this->getJson("/api/v1/online-store/orders/{$id}")->assertNotFound();
        $this->assertSame(0, $this->getJson('/api/v1/online-store/orders?status=cancelled')->json('meta.total'));
    }

    public function test_an_erased_customer_leaves_their_orders(): void
    {
        $this->order()->assertCreated();
        $customer = $this->getJson('/api/v1/customers?q=أحمد')->json('data.0.id');
        $this->postJson("/api/v1/customers/{$customer}/erase")->assertOk();
        app(EventRelay::class)->publishPending();

        $order = $this->getJson('/api/v1/online-store/orders')->json('data.0');
        $this->assertSame(['عميل محذوف', ''], [$order['customer_name'], $order['customer_phone']]);
    }
}
