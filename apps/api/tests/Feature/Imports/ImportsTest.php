<?php

namespace Tests\Feature\Imports;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Imports\Support\LandedCost;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** «الاستيراد»: contacts and statements, shipments, landed cost, claims, payments. */
class ImportsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    private string $branch;

    /** @var array<string, string> */
    private array $v = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = $this->registerShop(ShopType::Importer);
        app(EventRelay::class)->publishPending();
        Sanctum::actingAs($this->owner);
        app(PermissionResolver::class)->forget();
        $this->branch = $this->inShop(fn () => Branch::query()->value('id'));
        $category = $this->inShop(fn () => Category::query()->value('id'));
        foreach (['case' => 'جراب', 'charger' => 'شاحن'] as $key => $name) {
            $this->v[$key] = $this->postJson('/api/v1/products', ['name' => $name, 'category_id' => $category, 'variants' => [['price_retail' => 10000]]])
                ->assertCreated()->json('data.variants.0.id');
        }
    }

    private function inShop(callable $fn): mixed
    {
        return app(CurrentTenant::class)->runAs($this->owner->tenant_id, $fn);
    }

    private function createStaff(string $role): User
    {
        $id = $this->postJson('/api/v1/users', [
            'name' => $role, 'phone' => '011'.random_int(10000000, 99999999), 'password' => 'password',
            'role_id' => $this->inShop(fn () => Role::query()->where('key', $role)->value('id')), 'branch_ids' => [$this->branch],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }

    private function contact(string $type, string $name): string
    {
        return $this->postJson('/api/v1/imports/contacts', ['type' => $type, 'name' => $name, 'country' => 'الصين'])->assertCreated()->json('data.id');
    }

    private function balance(string $id): int
    {
        return $this->getJson("/api/v1/imports/contacts/{$id}")->json('data.balance');
    }

    public function test_a_shipment_from_order_to_stock_at_landed_cost(): void
    {
        $factory = $this->contact('supplier', 'Shenzhen Co');
        $shipping = $this->contact('shipping', 'شركة الشحن');

        // 100 cases × 20 ج + 50 chargers × 60 ج = 5,000 ج owed to the factory.
        $s = $this->postJson('/api/v1/imports/shipments', [
            'contact_id' => $factory, 'branch_id' => $this->branch, 'ordered_on' => '2026-10-01', 'expected_on' => '2026-11-01',
            'original_amount' => '100 USD',
            'items' => [['variant_id' => $this->v['case'], 'qty' => 100, 'unit_price' => 2000], ['variant_id' => $this->v['charger'], 'qty' => 50, 'unit_price' => 6000]],
        ])->assertCreated()->json('data');
        $this->assertSame(['IMP-00001', 'ordered', 500000], [$s['reference'], $s['status'], $s['goods_total']]);
        $this->assertSame(500000, $this->balance($factory));

        // Edited before arrival: the statement follows (100 → 120 cases).
        $this->putJson("/api/v1/imports/shipments/{$s['id']}", ['items' => [
            ['variant_id' => $this->v['case'], 'qty' => 120, 'unit_price' => 2000], ['variant_id' => $this->v['charger'], 'qty' => 50, 'unit_price' => 6000],
        ]])->assertOk();
        $this->assertSame(540000, $this->balance($factory));

        // Costs: shipping owed to the shipping company, customs paid on the spot.
        $this->postJson("/api/v1/imports/shipments/{$s['id']}/costs", ['kind' => 'shipping', 'amount' => 54000, 'contact_id' => $shipping])->assertOk();
        $withCosts = $this->postJson("/api/v1/imports/shipments/{$s['id']}/costs", ['kind' => 'customs', 'amount' => 27000])->assertOk()->json('data');
        $this->assertSame([81000, 54000], [$withCosts['costs_total'], $this->balance($shipping)]);

        foreach (['shipped', 'customs', 'arrived'] as $status) {
            $this->postJson("/api/v1/imports/shipments/{$s['id']}/status", ['status' => $status])->assertOk();
        }
        $this->postJson("/api/v1/imports/shipments/{$s['id']}/status", ['status' => 'received'])->assertUnprocessable();

        // 115 good cases + 3 broken (2 missing), all chargers; shortages claimed.
        $items = collect($withCosts['items'])->keyBy('variant_id');
        $received = $this->postJson("/api/v1/imports/shipments/{$s['id']}/receive", ['claim' => true, 'lines' => [
            ['item_id' => $items[$this->v['case']]['id'], 'received' => 115, 'damaged' => 3],
            ['item_id' => $items[$this->v['charger']]['id'], 'received' => 50],
        ]])->assertOk()->json('data');
        $this->assertSame('received', $received['status']);

        // Costs spread by value: cases 2,400 / 5,400 of 810 ج = 360 ج → (115 × 20 + 360) / 115.
        $lines = collect($received['items'])->keyBy('variant_id');
        $this->assertSame(intdiv(115 * 2000 + 36000 + 57, 115), $lines[$this->v['case']]['landed_unit_cost']);
        $this->assertSame(6000 + intdiv(45000, 50), $lines[$this->v['charger']]['landed_unit_cost']);
        $stock = $this->inShop(fn () => app(StockLedger::class)->quantities($this->branch, array_values($this->v)));
        $this->assertSame([115, 50], [$stock[$this->v['case']], $stock[$this->v['charger']]]);
        // The 5 cases (3 broken + 2 missing) come off the factory's account.
        $this->assertSame(540000 - 5 * 2000, $this->balance($factory));
        $this->assertDatabaseHas('audit_log', ['action' => 'imports.short']);

        // Closed now.
        $this->postJson("/api/v1/imports/shipments/{$s['id']}/costs", ['kind' => 'other', 'amount' => 100])->assertUnprocessable()->assertJsonPath('code', 'shipment_closed');
    }

    public function test_payments_with_a_receipt_and_reversal(): void
    {
        $factory = $this->contact('supplier', 'Shenzhen Co');
        $this->postJson('/api/v1/imports/shipments', [
            'contact_id' => $factory, 'branch_id' => $this->branch, 'ordered_on' => '2026-10-01',
            'items' => [['variant_id' => $this->v['case'], 'qty' => 10, 'unit_price' => 10000]],
        ])->assertCreated();

        $paid = $this->post('/api/v1/imports/payments', [
            'contact_id' => $factory, 'amount' => 40000, 'method' => 'exchange', 'paid_on' => '2026-10-02',
            'received_by' => 'Mr. Li', 'reference' => 'WU-123', 'proof' => UploadedFile::fake()->image('r.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame([60000, true], [$this->balance($factory), $paid['has_proof']]);
        $this->get("/api/v1/imports/payments/{$paid['id']}/proof")->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->postJson("/api/v1/imports/payments/{$paid['id']}/reverse", ['reason' => 'اتكتب غلط'])->assertOk();
        $this->postJson("/api/v1/imports/payments/{$paid['id']}/reverse", ['reason' => 'تاني'])->assertUnprocessable();
        $statement = $this->getJson("/api/v1/imports/contacts/{$factory}")->json('data');
        $this->assertSame(100000, $statement['balance']);
        $this->assertSame(['reversal', 'payment', 'shipment'], array_column($statement['statement'], 'type'));
        $this->assertSame('IMP-00001', $statement['statement'][2]['note']);
    }

    public function test_cancel_unwinds_the_statements_and_attachments_are_private(): void
    {
        $factory = $this->contact('supplier', 'Factory');
        $broker = $this->contact('customs', 'المخلّص');
        $s = $this->postJson('/api/v1/imports/shipments', [
            'contact_id' => $factory, 'branch_id' => $this->branch, 'ordered_on' => '2026-10-01',
            'items' => [['variant_id' => $this->v['case'], 'qty' => 10, 'unit_price' => 1000]],
        ])->json('data');
        $this->postJson("/api/v1/imports/shipments/{$s['id']}/costs", ['kind' => 'clearance', 'amount' => 5000, 'contact_id' => $broker])->assertOk();

        $withFile = $this->post("/api/v1/imports/shipments/{$s['id']}/attachments", ['kind' => 'invoice', 'file' => UploadedFile::fake()->create('inv.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('data');
        $this->get("/api/v1/imports/shipments/{$s['id']}/attachments/{$withFile['attachments'][0]['id']}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post("/api/v1/imports/shipments/{$s['id']}/attachments", ['kind' => 'other', 'file' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->postJson("/api/v1/imports/shipments/{$s['id']}/cancel", ['reason' => 'المصنع لغى'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame([0, 0], [$this->balance($factory), $this->balance($broker)]);

        // A shipping company isn't a supplier.
        $this->postJson('/api/v1/imports/shipments', [
            'contact_id' => $broker, 'branch_id' => $this->branch, 'ordered_on' => '2026-10-01',
            'items' => [['variant_id' => $this->v['case'], 'qty' => 1, 'unit_price' => 1]],
        ])->assertUnprocessable()->assertJsonPath('code', 'contact_not_supplier');
    }

    public function test_landed_cost_shares_add_up_exactly(): void
    {
        $this->assertSame([34, 33, 33], LandedCost::split(100, [1, 1, 1]));
        $this->assertSame(1001, array_sum(LandedCost::split(1001, [7, 3, 11, 2])));
        // Not claimed: the missing units' price falls on the good ones.
        $this->assertSame([['share' => 100, 'unit_cost' => 1263]], LandedCost::compute([['qty' => 10, 'unit_price' => 1000, 'received' => 8]], 100, 'value', false));
        $this->assertSame([['share' => 100, 'unit_cost' => 1013]], LandedCost::compute([['qty' => 10, 'unit_price' => 1000, 'received' => 8]], 100, 'value', true));
    }

    public function test_the_summary_and_staff_without_the_permission(): void
    {
        $factory = $this->contact('supplier', 'Factory');
        $this->postJson('/api/v1/imports/shipments', [
            'contact_id' => $factory, 'branch_id' => $this->branch, 'ordered_on' => '2026-09-01', 'expected_on' => '2026-09-15',
            'items' => [['variant_id' => $this->v['case'], 'qty' => 10, 'unit_price' => 1000]],
        ])->assertCreated();
        $summary = $this->getJson('/api/v1/imports/summary')->assertOk()->json('data');
        $this->assertSame([10000, 1, 1], [$summary['owed'], $summary['on_the_way'], $summary['late']]);
        $this->assertSame(1, $this->getJson('/api/v1/imports/shipments?status=late')->json('meta.total'));

        $cashier = $this->createStaff('cashier');
        Sanctum::actingAs($cashier);
        app(PermissionResolver::class)->forget();
        $this->getJson('/api/v1/imports/summary')->assertForbidden();
    }
}
