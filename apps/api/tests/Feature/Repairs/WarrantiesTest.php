<?php

namespace Tests\Feature\Repairs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class WarrantiesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift();
    }

    /** A repaired ticket with the charger fitted, ready to deliver. */
    private function ready(): array
    {
        $id = $this->postJson('/api/v1/repairs/tickets', ['customer_name' => 'محمد', 'customer_phone' => '01012345678', 'device_name' => 'iPhone 13', 'reported_note' => 'مش بيشحن'])->assertCreated()->json('data.id');
        $url = "/api/v1/repairs/tickets/{$id}";
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->postJson("{$url}/parts", ['variant_id' => $this->v[1], 'qty' => 1])->assertOk();
        $this->patchJson($url, ['labor' => 20000])->assertOk();

        return $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk()->json('data');
    }

    public function test_each_part_and_the_labor_have_their_own_warranty(): void
    {
        $this->putJson('/api/v1/repairs/warranty-settings', ['warranty_terms' => 'الضمان على القطعة بس.', 'labor_warranty_days' => 14])->assertOk();
        $ticket = $this->ready();
        $this->assertSame(14, $ticket['warranty_defaults']['labor_warranty_days']);
        $part = $ticket['parts'][0];

        $done = $this->postJson("/api/v1/repairs/tickets/{$ticket['id']}/deliver", [
            'payments' => [['method' => 'cash', 'amount' => $ticket['due']]],
            'warranties' => [['part_id' => $part['id'], 'days' => 90], ['part_id' => null, 'days' => 14]],
        ])->assertOk()->json('data');
        $this->assertSame([[$part['name'], 90, 'part'], ['المصنعية', 14, 'labor']], array_map(fn ($w) => [$w['name'], $w['days'], $w['kind']], $done['warranties']));
        $this->assertSame(90, $done['warranty_days']);
        $this->assertTrue($done['under_warranty']);

        // The list and its counts; searching by phone.
        $list = $this->getJson('/api/v1/repairs/warranties')->assertOk()->json();
        $this->assertCount(2, $list['data']);
        $this->assertSame(1, $list['meta']['summary']['active']);
        $this->assertCount(2, $this->getJson('/api/v1/repairs/warranties?q=0101234')->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/repairs/warranties?status=soon')->json('data'));

        // Voiding the part leaves the labor's 14 days on the ticket.
        $this->postJson("/api/v1/repairs/warranties/{$done['warranties'][0]['id']}/void", ['reason' => 'الجهاز وقع'])->assertOk()->assertJsonPath('data.state', 'voided');
        $this->assertSame(14, $this->getJson("/api/v1/repairs/tickets/{$ticket['id']}")->json('data.warranty_days'));
        $this->assertCount(1, $this->getJson('/api/v1/repairs/warranties?status=voided')->json('data'));

        // Came back: listed as claimed and counted.
        $this->postJson("/api/v1/repairs/tickets/{$ticket['id']}/warranty")->assertCreated();
        $this->assertSame(1, $this->getJson('/api/v1/repairs/warranties?status=claimed')->json('meta.summary.claims_month'));

        // The tracking page shows the items and the terms.
        $public = $this->getJson("/api/v1/public/repairs/{$done['public_token']}")->assertOk()->json('data');
        $this->assertSame('الضمان على القطعة بس.', $public['warranty_terms']);
        $this->assertCount(2, $public['warranties']);
    }

    public function test_the_old_way_still_works_and_only_managers_void(): void
    {
        $ticket = $this->ready();
        $done = $this->postJson("/api/v1/repairs/tickets/{$ticket['id']}/deliver", ['payments' => [['method' => 'cash', 'amount' => $ticket['due']]], 'warranty_days' => 30])->assertOk()->json('data');
        $this->assertSame([['الصيانة كلها', 30]], array_map(fn ($w) => [$w['name'], $w['days']], $done['warranties']));

        Sanctum::actingAs($this->staff('technician'));
        $this->postJson("/api/v1/repairs/warranties/{$done['warranties'][0]['id']}/void", ['reason' => 'x'])->assertForbidden();
        $this->putJson('/api/v1/repairs/warranty-settings', ['labor_warranty_days' => 7])->assertForbidden();
    }

    public function test_a_products_warranty_days_are_the_parts_default(): void
    {
        $product = $this->getJson('/api/v1/products?q=')->json('data.0.id');
        $this->assertNotNull($product);
        $this->patchJson("/api/v1/products/{$product}", ['warranty_days' => 60])->assertOk();
        $this->assertSame(60, $this->getJson("/api/v1/products/{$product}")->json('data.warranty_days'));
    }
}
