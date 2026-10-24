<?php

namespace Tests\Feature\Repairs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class CustomerDevicesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift();
    }

    private function receive(array $body): array
    {
        return $this->postJson('/api/v1/repairs/tickets', ['reported_note' => 'مش بيشحن', ...$body])->assertCreated()->json('data');
    }

    public function test_tickets_hang_off_the_customers_devices(): void
    {
        $first = $this->receive(['customer_name' => 'محمد', 'customer_phone' => '01012345678', 'device_name' => 'iPhone 13', 'color' => 'أزرق']);
        $customer = $first['customer_id'];
        // Same model and colour → the same device; another IMEI → another device.
        $second = $this->receive(['customer_id' => $customer, 'device_name' => 'iphone 13', 'color' => 'أزرق']);
        $third = $this->receive(['customer_id' => $customer, 'device_name' => 'Samsung A54', 'imei' => '351234567890125']);
        $this->assertSame($first['device_id'], $second['device_id']);
        $this->assertNotSame($first['device_id'], $third['device_id']);
        // The same IMEI later finds it, whatever the name was typed as.
        $this->assertSame($third['device_id'], $this->receive(['customer_id' => $customer, 'device_name' => 'A54', 'imei' => '351234567890125'])['device_id']);
        // Picked from the list.
        $this->assertSame($first['device_id'], $this->receive(['customer_id' => $customer, 'device_id' => $first['device_id'], 'device_name' => 'iPhone 13'])['device_id']);

        $devices = $this->getJson("/api/v1/repairs/devices?customer_id={$customer}")->assertOk()->json('data');
        $this->assertCount(2, $devices);
        $iphone = collect($devices)->firstWhere('id', $first['device_id']);
        $this->assertSame([3, 'iPhone 13'], [$iphone['tickets_count'], $iphone['name']]);
        $this->assertSame(['مش بيشحن'], array_unique(array_column($iphone['tickets'], 'note')));
        $this->assertNotNull($iphone['open_ticket_id']);

        // Another customer's device can't be picked.
        $other = $this->receive(['customer_name' => 'سارة', 'customer_phone' => '01098765432', 'device_name' => 'Redmi 12']);
        $this->postJson('/api/v1/repairs/tickets', ['customer_id' => $customer, 'device_id' => $other['device_id'], 'device_name' => 'x', 'reported_note' => 'x'])
            ->assertNotFound()->assertJsonPath('code', 'device_not_found');

        $this->patchJson("/api/v1/repairs/devices/{$first['device_id']}", ['name' => 'iPhone 13 Pro', 'notes' => 'الكاميرا فيها خط'])->assertOk()->assertJsonPath('data.notes', 'الكاميرا فيها خط');
    }

    public function test_a_warranty_return_stays_on_the_same_device(): void
    {
        $ticket = $this->receive(['customer_name' => 'محمد', 'customer_phone' => '01012345678', 'device_name' => 'iPhone 13']);
        $url = "/api/v1/repairs/tickets/{$ticket['id']}";
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->patchJson($url, ['labor' => 20000])->assertOk();
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk();
        $this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => 20000]], 'warranty_days' => 30])->assertOk();

        $device = $this->getJson("/api/v1/repairs/devices/{$ticket['device_id']}")->assertOk()->json('data');
        $this->assertSame($ticket['id'], $device['warranty_ticket_id']);
        $back = $this->postJson("{$url}/warranty")->assertCreated()->json('data');
        $this->assertSame($ticket['device_id'], $back['device_id']);

        Sanctum::actingAs($this->staff('technician'));
        $this->getJson("/api/v1/repairs/devices?customer_id={$ticket['customer_id']}")->assertOk();
    }

    public function test_how_it_came_in_is_filled_in_after_the_intake(): void
    {
        $ticket = $this->receive(['customer_name' => 'محمد', 'customer_phone' => '01012345678', 'device_name' => 'iPhone 13']);
        $after = $this->patchJson("/api/v1/repairs/tickets/{$ticket['id']}", [
            'imei' => '356789104512338', 'color' => 'أزرق',
            'accessories' => ['charger'], 'condition' => ['scratches'], 'checks' => ['powers_on' => 'yes', 'touch' => 'no'],
            'unlock_type' => 'pattern', 'unlock_code' => '1-5-9',
        ])->assertOk()->json('data');
        $this->assertSame([['scratches'], 'no', 'pattern', '1-5-9', '356789104512338'], [$after['condition'], $after['checks']['touch'], $after['unlock_type'], $after['unlock_code'], $after['imei']]);
        $this->assertDatabaseHas('repair_devices', ['id' => $ticket['device_id'], 'imei' => '356789104512338', 'color' => 'أزرق']);
        $this->patchJson("/api/v1/repairs/tickets/{$ticket['id']}", ['condition' => ['flying']])->assertUnprocessable();
    }
}
