<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceChange;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class BulkPricesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    /** @var array<string, string> name => variant id */
    private array $v = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->registerShop(ShopType::AccessoriesAndRepair);
        app(EventRelay::class)->publishPending();
        Sanctum::actingAs($this->owner);

        $cases = $this->inShop(fn () => Category::query()->where('name', 'جرابات')->value('id'));
        $screens = $this->inShop(fn () => Category::query()->where('name', 'سكرينات حماية')->value('id'));
        $brand = $this->inShop(fn () => Brand::query()->value('id'));

        $this->product('جراب سيليكون', $cases, [['name' => 'أسود', 'price_retail' => 10000, 'price_wholesale' => 7000], ['name' => 'أحمر', 'price_retail' => 12000]], $brand);
        $this->product('سكرينة 9D', $screens, [['name' => null, 'price_retail' => 5000, 'price_wholesale' => 3000]]);
    }

    private function inShop(callable $callback): mixed
    {
        return app(CurrentTenant::class)->runAs($this->owner->tenant_id, $callback);
    }

    private function staff(string $roleId): User
    {
        $id = $this->postJson('/api/v1/users', [
            'name' => 'موظف',
            'phone' => '011'.random_int(10000000, 99999999),
            'password' => 'password',
            'role_id' => $roleId,
            'branch_ids' => [$this->inShop(fn () => Branch::query()->value('id'))],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }

    private function product(string $name, int $categoryId, array $variants, ?int $brandId = null): void
    {
        $data = $this->postJson('/api/v1/products', ['name' => $name, 'category_id' => $categoryId, 'brand_id' => $brandId, 'variants' => $variants])
            ->assertCreated()->json('data');
        foreach ($data['variants'] as $variant) {
            $this->v[trim($name.' '.($variant['name'] ?? ''))] = $variant['id'];
        }
    }

    private function prices(string $key): array
    {
        $productId = $this->getJson('/api/v1/products?q='.urlencode(explode(' ', $key)[0]))->json('data.0.id');
        $variant = collect($this->getJson("/api/v1/products/{$productId}")->json('data.variants'))->firstWhere('id', $this->v[$key]);

        return [$variant['price_retail'], $variant['price_wholesale']];
    }

    public function test_preview_shows_what_would_change_without_changing_it(): void
    {
        $res = $this->postJson('/api/v1/products/prices/preview', [
            'field' => 'price_retail', 'base' => 'price_retail', 'change' => 'percent', 'value' => 12.5, 'round_to' => 500, 'rounding' => 'up',
        ])->assertOk()->json('data');

        $rows = collect($res['rows'])->keyBy('variant_id');
        $this->assertSame([10000, 12000], [$rows[$this->v['جراب سيليكون أسود']]['old'], $rows[$this->v['جراب سيليكون أحمر']]['old']]);
        // 11250 → 11500, 13500 stays, 5625 → 6000
        $this->assertSame([11500, 13500, 6000], [$rows[$this->v['جراب سيليكون أسود']]['new'], $rows[$this->v['جراب سيليكون أحمر']]['new'], $rows[$this->v['سكرينة 9D']]['new']]);
        $this->assertSame(['matched' => 3, 'changing' => 3, 'skipped' => 0, 'below_cost' => 0, 'raised' => 3, 'lowered' => 0], $res['summary']);
        $this->assertSame('سعر القطاعي: سعر القطاعي +12.5%', $res['description']);
        $this->assertSame([10000, 7000], $this->prices('جراب سيليكون أسود'), 'a preview changes nothing');
    }

    public function test_apply_by_category_with_history_and_audit(): void
    {
        $cases = $this->inShop(fn () => Category::query()->where('name', 'جرابات')->value('id'));

        // Wholesale = retail − 20 EGP, only for cases, leaving the red one out.
        $this->postJson('/api/v1/products/prices', [
            'category_id' => $cases, 'field' => 'price_wholesale', 'base' => 'price_retail', 'change' => 'amount', 'value' => -2000,
            'exclude' => [$this->v['جراب سيليكون أحمر']],
        ])->assertOk()->assertJsonPath('data.changed', 1);

        $this->assertSame([10000, 8000], $this->prices('جراب سيليكون أسود'));
        $this->assertSame([12000, null], $this->prices('جراب سيليكون أحمر'));
        $this->assertSame([5000, 3000], $this->prices('سكرينة 9D'), 'another category');

        $change = $this->inShop(fn () => PriceChange::query()->sole());
        $this->assertSame(['price_wholesale', 7000, 8000, 'bulk'], [$change->field, $change->old_price, $change->new_price, $change->source]);
        $this->assertSame('عدّل أسعار 1 صنف — سعر الجملة: سعر القطاعي −20 ج', AuditEntry::query()->where('action', 'products.prices_bulk_updated')->value('description'));
        $this->expectException(LogicException::class);
        $this->inShop(fn () => $change->delete());
    }

    public function test_a_fixed_price_a_search_and_nothing_to_change(): void
    {
        $this->postJson('/api/v1/products/prices', ['q' => 'سكرينه', 'field' => 'price_retail', 'change' => 'set', 'value' => 6000])
            ->assertOk()->assertJsonPath('data.changed', 1);
        $this->assertSame([6000, 3000], $this->prices('سكرينة 9D'));

        $this->postJson('/api/v1/products/prices', ['q' => 'سكرينه', 'field' => 'price_retail', 'change' => 'set', 'value' => 6000])
            ->assertStatus(422)->assertJsonPath('code', 'nothing_to_change');

        // Wholesale from wholesale: the red one has none, so it is skipped.
        $rows = $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_wholesale', 'base' => 'price_wholesale', 'change' => 'percent', 'value' => 10])
            ->json('data.rows');
        $this->assertSame('no_base', collect($rows)->firstWhere('variant_id', $this->v['جراب سيليكون أحمر'])['skip']);
    }

    public function test_margin_over_the_average_cost_and_below_cost_warning(): void
    {
        $branchId = $this->inShop(fn () => Branch::query()->value('id'));
        $this->inShop(fn () => app(StockLedger::class)->receive($branchId, $this->v['جراب سيليكون أسود'], 10, 8000, new StockReference(MovementType::Adjustment)));
        $this->inShop(fn () => app(StockLedger::class)->receive($branchId, $this->v['سكرينة 9D'], 10, 6000, new StockReference(MovementType::Adjustment)));

        $res = $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'base' => 'cost', 'change' => 'percent', 'value' => 50])->json('data');
        $rows = collect($res['rows'])->keyBy('variant_id');
        $this->assertSame([12000, 8000], [$rows[$this->v['جراب سيليكون أسود']]['new'], $rows[$this->v['جراب سيليكون أسود']]['cost']]);
        $this->assertSame('no_cost', $rows[$this->v['جراب سيليكون أحمر']]['skip']);

        // Lowering screens by 20 EGP puts them under their 60 EGP cost.
        $res = $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'base' => 'price_retail', 'change' => 'amount', 'value' => -2000])->json('data');
        $this->assertTrue(collect($res['rows'])->firstWhere('variant_id', $this->v['سكرينة 9D'])['below_cost']);
        $this->assertSame(1, $res['summary']['below_cost']);
    }

    public function test_rules_and_permissions(): void
    {
        $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_cost', 'change' => 'percent', 'value' => 10])->assertUnprocessable()->assertJsonValidationErrors(['field', 'base']);
        $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'base' => 'price_retail', 'change' => 'percent', 'value' => -95])->assertUnprocessable();
        $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'base' => 'price_retail', 'change' => 'percent', 'value' => 5, 'round_to' => 300])->assertUnprocessable();

        // A manager with products.manage but not products.view_cost can't start from the cost, nor sees costs.
        $roleId = $this->postJson('/api/v1/roles', ['name' => 'مدير أصناف', 'permissions' => ['products.view', 'products.manage']])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->staff($roleId));
        $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'base' => 'cost', 'change' => 'percent', 'value' => 50])->assertForbidden();
        $row = $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'base' => 'price_retail', 'change' => 'percent', 'value' => 5])->assertOk()->json('data.rows.0');
        $this->assertNull($row['cost']);

        $productId = $this->getJson('/api/v1/products')->json('data.0.id');
        $this->getJson("/api/v1/products/{$productId}/prices")->assertOk();
    }

    public function test_editing_a_product_writes_the_price_history(): void
    {
        $product = $this->getJson('/api/v1/products?q=سكرينة')->json('data.0');
        $variant = $product['variants'][0];
        $this->patchJson("/api/v1/products/{$product['id']}", ['variants' => [[...array_intersect_key($variant, array_flip(['id', 'name', 'barcode', 'price_wholesale'])), 'price_retail' => 5500]]])->assertOk();

        $history = $this->getJson("/api/v1/products/{$product['id']}/prices")->assertOk()->json('data');
        $this->assertSame([['price_retail', 'سعر القطاعي', 5000, 5500, 'edit']], array_map(fn ($h) => [$h['field'], $h['field_label'], $h['old_price'], $h['new_price'], $h['source']], $history));

        Sanctum::actingAs($this->staff($this->inShop(fn () => Role::query()->where('key', 'cashier')->value('id'))));
        $this->getJson("/api/v1/products/{$product['id']}/prices")->assertForbidden();
        $this->postJson('/api/v1/products/prices/preview', ['field' => 'price_retail', 'change' => 'set', 'value' => 1])->assertForbidden();
    }
}
