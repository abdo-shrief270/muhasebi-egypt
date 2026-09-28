<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class StaffAndPermissionsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->actingAsOwnerOf(ShopType::AccessoriesAndRepair);
    }

    private function roleId(string $key, ?User $owner = null): int
    {
        $owner ??= $this->owner;

        return app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => Role::query()->where('key', $key)->value('id'));
    }

    private function mainBranchId(): string
    {
        return $this->getJson('/api/v1/branches')->json('data.0.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function addEmployee(string $roleKey, string $phone = '01122223333'): array
    {
        return $this->postJson('/api/v1/users', [
            'name' => 'أحمد الكاشير',
            'phone' => $phone,
            'password' => 'secret-pass',
            'role_id' => $this->roleId($roleKey),
            'branch_ids' => [$this->mainBranchId()],
        ])->assertCreated()->json('data');
    }

    public function test_every_shop_starts_with_default_roles(): void
    {
        $roles = $this->getJson('/api/v1/roles')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['manager', 'cashier', 'technician', 'storekeeper'], array_column($roles, 'key'));
    }

    public function test_the_owner_adds_an_employee_who_can_log_in_with_only_their_role_permissions(): void
    {
        $employee = $this->addEmployee('cashier');
        $this->assertSame('+201122223333', $employee['phone']);
        $this->assertSame('كاشير', $employee['role']['name']);

        $token = $this->postJson('/api/v1/auth/login', ['phone' => '01122223333', 'password' => 'secret-pass', 'device_name' => 'pos'])
            ->assertOk()->json('token');

        $this->app['auth']->forgetGuards();
        $me = $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->json('data');

        $this->assertContains('sales.sell', $me['permissions']);
        $this->assertNotContains('users.manage', $me['permissions']);
        $this->assertContains('/pos', array_column($me['menu'], 'to'));
        $this->assertNotContains('/reports', array_column($me['menu'], 'to'), 'the menu only shows what the user may open');
    }

    public function test_a_cashier_cannot_manage_staff_roles_or_branches(): void
    {
        $employee = User::query()->findOrFail($this->addEmployee('cashier')['id']);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/users')->assertForbidden()->assertJson(['code' => 'forbidden', 'message' => 'مش مسموح لك تعمل كده.']);
        $this->postJson('/api/v1/roles', ['name' => 'x', 'permissions' => []])->assertForbidden();
        $this->postJson('/api/v1/branches', ['name' => 'فرع'])->assertForbidden();
        $this->getJson('/api/v1/audit-log')->assertForbidden();
    }

    public function test_permissions_of_modules_the_shop_does_not_use_are_never_granted(): void
    {
        $this->postJson('/api/v1/roles', ['name' => 'مستورد', 'permissions' => ['imports.manage', 'products.view', 'made.up']])
            ->assertCreated()
            ->assertJsonPath('data.permissions', ['imports.manage', 'products.view']);

        $groups = array_column($this->getJson('/api/v1/permissions')->assertOk()->json('data'), 'module');
        $this->assertContains('repairs', $groups);
        $this->assertNotContains('imports', $groups, 'imports is not enabled for this shop');

        $role = Role::query()->where('tenant_id', $this->owner->tenant_id)->where('name', 'مستورد')->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $this->owner->tenant_id, 'role_id' => $role->id]);
        Sanctum::actingAs($user);

        $this->assertSame(['products.view'], $this->getJson('/api/v1/auth/me')->json('data.permissions'));
    }

    public function test_route_permissions_follow_the_role(): void
    {
        $technician = User::query()->findOrFail($this->addEmployee('technician')['id']);
        $cashier = User::query()->findOrFail($this->addEmployee('cashier', '01144445555')['id']);

        Sanctum::actingAs($technician);
        $this->getJson('/api/v1/repairs/fault-categories')->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/shop-orders/partners')->assertNotFound();
        $this->postJson('/api/v1/shop-connections', ['code' => 'AAAAAA'])->assertForbidden();
    }

    public function test_deactivating_an_employee_logs_them_out_and_blocks_login(): void
    {
        $employee = $this->addEmployee('cashier');
        $token = User::query()->findOrFail($employee['id'])->createToken('pos')->plainTextToken;

        $this->patchJson("/api/v1/users/{$employee['id']}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['phone' => '01122223333', 'password' => 'secret-pass', 'device_name' => 'pos'])
            ->assertUnprocessable();
    }

    public function test_the_owner_cannot_be_deactivated_or_given_a_role(): void
    {
        $this->patchJson("/api/v1/users/{$this->owner->id}", ['is_active' => false])
            ->assertUnprocessable()->assertJson(['code' => 'owner_immutable']);
    }

    public function test_employees_of_another_shop_are_invisible(): void
    {
        $otherOwner = $this->registerShop();

        $this->patchJson("/api/v1/users/{$otherOwner->id}", ['name' => 'hacked'])->assertNotFound();
        $this->assertNotContains($otherOwner->id, array_column($this->getJson('/api/v1/users')->json('data'), 'id'));
        $this->assertSame($otherOwner->name, $otherOwner->fresh()?->name);
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $this->addEmployee('cashier');

        $this->deleteJson('/api/v1/roles/'.$this->roleId('cashier'))->assertUnprocessable()->assertJson(['code' => 'role_in_use']);
        $this->deleteJson('/api/v1/roles/'.$this->roleId('storekeeper'))->assertNoContent();
    }

    public function test_validation_messages_are_arabic(): void
    {
        $this->postJson('/api/v1/users', ['phone' => '123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'الاسم مطلوب.')
            ->assertJsonPath('errors.phone.0', 'رقم الموبايل مش رقم موبايل صحيح.');
    }
}
