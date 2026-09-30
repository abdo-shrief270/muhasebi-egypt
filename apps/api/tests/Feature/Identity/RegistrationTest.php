<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Events\TenantRegistered;
use App\Modules\Identity\Models\User;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Events\StoredEvent;
use App\Support\Modules\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'shop_name' => 'محل النور',
            'shop_type' => 'repair',
            'owner_name' => 'أحمد',
            'phone' => '01012345678',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            ...$overrides,
        ];
    }

    public function test_a_shop_registers_with_its_main_branch_owner_and_recommended_module_trials(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'phone', 'is_owner']]);

        $owner = User::query()->firstOrFail();
        $this->assertSame('+201012345678', $owner->phone, 'phone is stored in E.164');
        $this->assertTrue($owner->is_owner);

        $this->assertDatabaseHas('branches', ['tenant_id' => $owner->tenant_id, 'is_main' => true]);

        $trials = TenantModule::withoutTenancy()->where('tenant_id', $owner->tenant_id)->pluck('state', 'module_key');
        $this->assertEqualsCanonicalizing(['repairs', 'shop_orders', 'supplier_returns', 'used_devices'], $trials->keys()->all());
        $this->assertTrue($trials->every(fn (ModuleState $state): bool => $state === ModuleState::Trial));

        $this->assertTrue(StoredEvent::query()->where('name', TenantRegistered::NAME)->where('tenant_id', $owner->tenant_id)->exists());
    }

    public function test_me_returns_only_the_modules_and_menu_the_shop_can_use(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();
        Sanctum::actingAs(User::query()->firstOrFail());

        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json('data');

        $this->assertContains('repairs', $me['enabled_modules']);
        $this->assertContains('sales', $me['enabled_modules'], 'core modules are always enabled');
        $this->assertNotContains('imports', $me['enabled_modules']);

        $menuTargets = array_column($me['menu'], 'to');
        $this->assertContains('/repairs', $menuTargets);
        $this->assertContains('/pos', $menuTargets);
        $this->assertNotContains('/imports', $menuTargets);
        $groups = array_column($me['menu'], 'group', 'to');
        $this->assertSame(['sales', 'stock', 'services'], [$groups['/pos'], $groups['/products'], $groups['/repairs']], 'the sidebar section each entry belongs to');
        $this->assertCount(1, $me['branches']);
    }

    public function test_an_accessories_shop_starts_with_core_modules_only(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['shop_type' => 'accessories']))->assertCreated();
        Sanctum::actingAs(User::query()->firstOrFail());

        $menuTargets = array_column($this->getJson('/api/v1/auth/me')->json('data.menu'), 'to');

        $this->assertNotContains('/repairs', $menuTargets);
        $this->assertNotContains('/imports', $menuTargets);
    }

    public function test_registration_is_validated(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['phone' => '123', 'shop_type' => 'bakery']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'shop_type']);

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_the_same_phone_cannot_register_twice(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();
        $this->postJson('/api/v1/auth/register', $this->payload(['phone' => '+201012345678']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_owner_can_log_in_with_local_phone_format(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'secret-pass', 'device_name' => 'pos-1'])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'wrong', 'device_name' => 'pos-1'])
            ->assertUnprocessable();
    }

    public function test_unauthenticated_api_calls_get_a_401_even_without_a_json_accept_header(): void
    {
        $this->get('/api/v1/auth/me')->assertUnauthorized()->assertJson(['code' => 'unauthenticated']);
    }
}
