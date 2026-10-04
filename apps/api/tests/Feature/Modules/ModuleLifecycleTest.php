<?php

namespace Tests\Feature\Modules;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Modules\Repairs\Models\FaultCategory;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleState;
use App\Support\Modules\ModuleTier;
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

        $this->postJson('/api/v1/modules/multi_branch/trial')->assertOk();
        $this->postJson('/api/v1/modules/multi_branch/disable')->assertOk();

        $this->postJson('/api/v1/modules/multi_branch/trial')
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

    /** A module whose screens aren't built yet (none in the codebase right now). */
    private function registerComingSoon(): void
    {
        app(ModuleRegistry::class)->register(new ModuleManifest(
            key: 'coming_soon',
            name: 'قريباً',
            tier: ModuleTier::Optional,
            permissions: ['coming_soon.manage' => 'تجربة'],
            menu: [new MenuItem('/coming-soon', 'قريباً', 'i-lucide-clock', 'coming_soon.manage', group: 'settings')],
            available: false,
        ));
    }

    public function test_the_modules_page_lists_optional_and_core_modules_with_their_state(): void
    {
        $this->actingAsOwnerOf(ShopType::Repair);
        $this->registerComingSoon();

        $modules = collect($this->getJson('/api/v1/modules')->assertOk()->json('data'))->keyBy('key');

        $this->assertSame('trial', $modules['repairs']['state']);
        $this->assertSame('not_entitled', $modules['multi_branch']['state']);
        $this->assertTrue($modules['multi_branch']['trial_available']);
        $this->assertSame([false, false], [$modules['coming_soon']['available'], $modules['coming_soon']['trial_available']], 'still being built');
        $this->assertFalse($modules->has('imports'), 'for importers, not repair shops');
        $this->assertContains('/transfers', array_column($modules['multi_branch']['menu'], 'to'));
        $this->assertSame('enabled', $modules['sales']['state']);
        $this->assertFalse($modules->has('identity'), 'platform modules are not shown to shops');
    }

    public function test_a_module_still_being_built_stays_off(): void
    {
        $owner = $this->actingAsOwnerOf(ShopType::Accessories);
        $this->registerComingSoon();

        $this->postJson('/api/v1/modules/coming_soon/trial')->assertStatus(409)->assertJsonPath('code', 'module_coming_soon');

        // A shop that started a trial before the module was marked «قريباً» doesn't get it either.
        TenantModule::withoutTenancy()->create(['tenant_id' => $owner->tenant_id, 'module_key' => 'coming_soon', 'entitled' => false, 'state' => ModuleState::Trial, 'source' => 'trial', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14), 'enabled_at' => now()]);
        app(ModuleAccess::class)->forget($owner->tenant_id);

        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json('data');
        $this->assertNotContains('coming_soon', $me['enabled_modules']);
        $this->assertNotContains('/coming-soon', array_column($me['menu'], 'to'));
        $this->assertNotContains('coming_soon.manage', $me['permissions']);
        $this->assertFalse(collect($me['modules'])->firstWhere('key', 'coming_soon')['available']);
    }

    public function test_only_the_owner_sees_the_modules_page(): void
    {
        $owner = $this->registerShop();
        Sanctum::actingAs(User::factory()->create(['tenant_id' => $owner->tenant_id]));

        $this->getJson('/api/v1/modules')->assertForbidden();
        $this->getJson('/api/v1/branches')->assertForbidden();
    }
}
