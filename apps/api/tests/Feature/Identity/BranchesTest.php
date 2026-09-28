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

class BranchesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_a_second_branch_needs_the_multi_branch_module(): void
    {
        $this->actingAsOwnerOf(ShopType::Accessories);

        $this->postJson('/api/v1/branches', ['name' => 'فرع المعادي'])
            ->assertForbidden()
            ->assertJson(['code' => 'multi_branch_required', 'module' => 'multi_branch']);

        $this->postJson('/api/v1/modules/multi_branch/trial')->assertOk();

        $this->postJson('/api/v1/branches', ['name' => 'فرع المعادي', 'phone' => '0225551234'])
            ->assertCreated()
            ->assertJsonPath('data.invoice_prefix', 'B2')
            ->assertJsonPath('data.is_main', false);
    }

    public function test_the_main_branch_cannot_be_deactivated(): void
    {
        $this->actingAsOwnerOf();
        $main = $this->getJson('/api/v1/branches')->json('data.0.id');

        $this->patchJson("/api/v1/branches/{$main}", ['is_active' => false])->assertUnprocessable()->assertJson(['code' => 'main_branch_immutable']);
        $this->patchJson("/api/v1/branches/{$main}", ['name' => 'الفرع الرئيسي - وسط البلد'])->assertOk();
    }

    public function test_employees_only_work_in_their_branches(): void
    {
        $owner = $this->actingAsOwnerOf();
        $this->postJson('/api/v1/modules/multi_branch/trial')->assertOk();
        $main = $this->getJson('/api/v1/branches')->json('data.0.id');
        $second = $this->postJson('/api/v1/branches', ['name' => 'فرع 2'])->json('data.id');

        $cashierRole = app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => Role::query()->where('key', 'cashier')->value('id'));
        $employeeId = $this->postJson('/api/v1/users', [
            'name' => 'منى', 'phone' => '01233334444', 'password' => 'secret-pass', 'role_id' => $cashierRole, 'branch_ids' => [$second],
        ])->assertCreated()->json('data.id');

        // The owner may work anywhere and defaults to the main branch.
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.current_branch_id', $main);
        $this->withHeader('X-Branch-Id', $second)->getJson('/api/v1/auth/me')->assertJsonPath('data.current_branch_id', $second);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::query()->findOrFail($employeeId));

        $me = $this->withHeader('X-Branch-Id', $second)->getJson('/api/v1/auth/me')->assertOk()->json('data');
        $this->assertSame([$second], array_column($me['branches'], 'id'));

        $this->withHeader('X-Branch-Id', $main)->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJson(['code' => 'branch_forbidden']);
    }
}
