<?php

namespace Tests\Feature\Modules;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Modules\Repairs\Models\FaultCategory;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class ModuleLifecycleTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_a_module_route_is_blocked_until_the_module_is_enabled(): void
    {
        $this->actingAsOwnerOf(ShopType::Accessories);

        $this->getJson('/api/v1/repairs/fault-categories')
            ->assertForbidden()
            ->assertJson(['code' => 'module_not_enabled', 'module' => 'repairs', 'state' => 'not_entitled']);
    }

    public function test_starting_a_trial_enables_the_module_and_its_listener_seeds_default_faults(): void
    {
        $this->actingAsOwnerOf(ShopType::Accessories);

        $this->postJson('/api/v1/modules/repairs/trial')
            ->assertOk()
            ->assertJsonPath('data.state', 'trial')
            ->assertJsonPath('data.usable', true);

        $categories = $this->getJson('/api/v1/repairs/fault-categories')->assertOk()->json('data');

        $this->assertCount(10, $categories);
        $this->assertSame('الشاشة', $categories[0]['name']);
        $this->assertContains('كسر زجاج', array_column($categories[0]['types'], 'name'));
    }

    public function test_a_trial_can_only_be_used_once(): void
    {
        $this->actingAsOwnerOf(ShopType::Accessories);

        $this->postJson('/api/v1/modules/imports/trial')->assertOk();
        $this->postJson('/api/v1/modules/imports/disable')->assertOk();

        $this->postJson('/api/v1/modules/imports/trial')
            ->assertUnprocessable()
            ->assertJson(['code' => 'module_trial_used']);
    }

    public function test_hiding_a_module_blocks_it_but_keeps_its_data_and_it_can_be_shown_again(): void
    {
        $owner = $this->actingAsOwnerOf(ShopType::Repair);
        $this->getJson('/api/v1/repairs/fault-categories')->assertOk();

        $this->postJson('/api/v1/modules/repairs/disable')->assertOk()->assertJsonPath('data.state', 'disabled');
        $this->getJson('/api/v1/repairs/fault-categories')->assertForbidden();
        $this->assertSame(10, app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => FaultCategory::query()->count()));

        $this->postJson('/api/v1/modules/repairs/enable')->assertOk()->assertJsonPath('data.usable', true);
        $this->assertCount(10, $this->getJson('/api/v1/repairs/fault-categories')->assertOk()->json('data'));
    }

    public function test_an_expired_trial_becomes_read_only(): void
    {
        $this->actingAsOwnerOf(ShopType::Repair);

        $this->travel(15)->days();

        $this->getJson('/api/v1/repairs/fault-categories')
            ->assertForbidden()
            ->assertJson(['state' => 'read_only']);
    }

    public function test_a_module_granted_by_the_subscription_stays_enabled_after_the_trial(): void
    {
        $owner = $this->actingAsOwnerOf(ShopType::Repair);
        app(TenantModules::class)->grant($owner->tenant_id, 'repairs', 'plan');

        $this->travel(30)->days();

        $this->getJson('/api/v1/repairs/fault-categories')->assertOk();
    }

    public function test_core_modules_cannot_be_hidden(): void
    {
        $this->actingAsOwnerOf();

        $this->postJson('/api/v1/modules/sales/disable')
            ->assertUnprocessable()
            ->assertJson(['code' => 'module_not_optional']);
    }

    public function test_only_the_owner_manages_modules(): void
    {
        $owner = $this->registerShop();
        Sanctum::actingAs(User::factory()->create(['tenant_id' => $owner->tenant_id]));

        $this->postJson('/api/v1/modules/repairs/trial')->assertForbidden();
    }

    public function test_the_modules_page_lists_optional_and_core_modules_with_their_state(): void
    {
        $this->actingAsOwnerOf(ShopType::Repair);

        $modules = collect($this->getJson('/api/v1/modules')->assertOk()->json('data'))->keyBy('key');

        $this->assertSame('trial', $modules['repairs']['state']);
        $this->assertSame('not_entitled', $modules['imports']['state']);
        $this->assertTrue($modules['imports']['trial_available']);
        $this->assertSame('enabled', $modules['sales']['state']);
        $this->assertFalse($modules->has('identity'), 'platform modules are not shown to shops');
    }
}
