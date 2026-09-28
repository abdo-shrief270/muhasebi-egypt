<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Repairs\Models\FaultCategory;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\MissingTenantException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_a_shop_never_sees_another_shops_data(): void
    {
        $shopA = $this->registerShop(ShopType::Repair);
        $shopB = $this->registerShop(ShopType::Repair);

        app(CurrentTenant::class)->runAs($shopB->tenant_id, fn () => FaultCategory::create(['name' => 'قسم خاص بمحل ب', 'sort' => 99]));

        Sanctum::actingAs($shopA);
        $names = array_column($this->getJson('/api/v1/repairs/fault-categories')->assertOk()->json('data'), 'name');

        $this->assertCount(10, $names);
        $this->assertNotContains('قسم خاص بمحل ب', $names);

        $branchTenants = app(CurrentTenant::class)->runAs($shopA->tenant_id, fn () => Branch::query()->pluck('tenant_id')->unique()->all());
        $this->assertSame([$shopA->tenant_id], $branchTenants);
    }

    public function test_tenant_scoped_queries_return_nothing_without_a_tenant(): void
    {
        $this->registerShop(ShopType::Repair);

        $this->assertSame(0, FaultCategory::query()->count());
        $this->assertGreaterThan(0, FaultCategory::withoutTenancy()->count());
    }

    public function test_creating_a_tenant_scoped_model_without_a_tenant_fails(): void
    {
        $this->expectException(MissingTenantException::class);

        FaultCategory::create(['name' => 'بدون محل']);
    }
}
