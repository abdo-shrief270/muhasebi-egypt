<?php

namespace Tests\Concerns;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Laravel\Sanctum\Sanctum;

/**
 * A shop with an owner signed in and two items in stock: a case (100 ج, cost 40) and a
 * charger (450 ج, cost 300).
 */
trait SellsInAShop
{
    use CreatesShops;

    private User $owner;

    private string $branchId;

    /** @var array{0: string, 1: string} */
    private array $v;

    protected function openShopWithStock(): void
    {
        $this->owner = $this->registerShop(ShopType::AccessoriesAndRepair);
        app(EventRelay::class)->publishPending();
        $this->branchId = $this->inShop(fn () => Branch::query()->value('id'));
        Sanctum::actingAs($this->owner);

        $this->v = [
            $this->variant('جراب', 'جرابات', ['barcode' => 'CASE-1', 'price_retail' => 10000]),
            $this->variant('شاحن 20W', 'شواحن', ['barcode' => 'CHG-1', 'price_retail' => 45000]),
        ];
        $this->postJson('/api/v1/inventory/opening', ['items' => [
            ['variant_id' => $this->v[0], 'qty' => 50, 'unit_cost' => 4000],
            ['variant_id' => $this->v[1], 'qty' => 20, 'unit_cost' => 30000],
        ]])->assertCreated();
    }

    private function inShop(callable $callback): mixed
    {
        return app(CurrentTenant::class)->runAs($this->owner->tenant_id, $callback);
    }

    private function variant(string $name, string $category, array $data): string
    {
        return $this->postJson('/api/v1/products', [
            'name' => $name,
            'category_id' => $this->inShop(fn () => Category::query()->where('name', $category)->value('id')),
            'variants' => [$data],
        ])->assertCreated()->json('data.variants.0.id');
    }

    /** One charger (450 ج) paid as given. */
    private function sell(array $payments, array $overrides = [])
    {
        return $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[1], 'qty' => 1]],
            'payments' => $payments,
            ...$overrides,
        ]);
    }

    private function openShift(int $openingCash = 0)
    {
        return $this->postJson('/api/v1/cash/shifts', ['opening_cash' => $openingCash])->assertCreated();
    }

    private function staff(string $role): User
    {
        $id = $this->postJson('/api/v1/users', [
            'name' => $role,
            'phone' => '011'.random_int(10000000, 99999999),
            'password' => 'password',
            'role_id' => $this->inShop(fn () => Role::query()->where('key', $role)->value('id')),
            'branch_ids' => [$this->branchId],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }
}
