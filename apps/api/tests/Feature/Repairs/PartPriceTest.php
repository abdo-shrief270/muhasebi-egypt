<?php

namespace Tests\Feature\Repairs;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class PartPriceTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private User $tech;

    private string $url;

    private int $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->tech = $this->staff('technician');
        $id = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'عميل', 'customer_phone' => '01234567890', 'device_name' => 'iPhone 13', 'reported_note' => 'مش بيشحن', 'technician_id' => $this->tech->id,
        ])->assertCreated()->json('data.id');
        $this->url = "/api/v1/repairs/tickets/{$id}";
        // The charger: 450 retail, 300 cost.
        $this->part = $this->postJson("{$this->url}/parts", ['variant_id' => $this->v[1], 'qty' => 1])->assertOk()->json('data.parts.0.id');
    }

    public function test_a_technician_changes_a_parts_price_for_the_customer(): void
    {
        Sanctum::actingAs($this->tech);
        $ticket = $this->patchJson("{$this->url}/parts/{$this->part}", ['unit_price' => 40000, 'note' => 'عميل قديم'])->assertOk()->json('data');
        $this->assertSame([40000, 45000, 'عميل قديم', 40000], [$ticket['parts'][0]['unit_price'], $ticket['parts'][0]['list_price'], $ticket['parts'][0]['price_note'], $ticket['parts_total']]);

        // Changed again: the shop's price stays the first one.
        $this->assertSame(45000, $this->patchJson("{$this->url}/parts/{$this->part}", ['unit_price' => 42000])->assertOk()->json('data.parts.0.list_price'));

        // Under the cost (the default floor) is a manager's call.
        $this->patchJson("{$this->url}/parts/{$this->part}", ['unit_price' => 29000])->assertStatus(422)->assertJsonPath('code', 'part_price_below_floor');
        Sanctum::actingAs($this->owner);
        $this->patchJson("{$this->url}/parts/{$this->part}", ['unit_price' => 29000])->assertOk();

        // With the floor switched off anyone allowed goes anywhere.
        $this->putJson('/api/v1/features/repairs.price_floor', ['enabled' => false])->assertOk();
        Sanctum::actingAs($this->tech);
        $this->patchJson("{$this->url}/parts/{$this->part}", ['unit_price' => 10000])->assertOk();

        $this->assertDatabaseHas('audit_log', ['action' => 'repairs.part_price']);
    }

    public function test_only_with_the_permission(): void
    {
        Sanctum::actingAs($this->staff('cashier'));
        $this->patchJson("{$this->url}/parts/{$this->part}", ['unit_price' => 40000])->assertForbidden();
    }
}
