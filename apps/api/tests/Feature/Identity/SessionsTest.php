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

class SessionsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private const OWNER_PHONE = '+201055550002';

    private function signIn(string $phone, string $password, string $device): string
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('User-Agent', "Test browser on {$device}")
            ->postJson('/api/v1/auth/login', ['phone' => $phone, 'password' => $password, 'device_name' => $device])
            ->assertOk()->json('token');
    }

    /** Next requests use this bearer token, looked up afresh. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function tokenId(string $token): int
    {
        return (int) explode('|', $token)[0];
    }

    /**
     * @return array{0: User, 1: string} the employee and their token
     */
    private function employeeOf(User $owner, string $role = 'cashier', string $phone = '01122223333'): array
    {
        $roleId = app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => Role::query()->where('key', $role)->value('id'));
        $employee = User::query()->create([
            'tenant_id' => $owner->tenant_id, 'name' => 'موظف', 'phone' => '+2'.$phone,
            'password' => 'secret-pass', 'role_id' => $roleId,
        ]);

        return [$employee, $this->signIn($phone, 'secret-pass', 'Cashier PC')];
    }

    public function test_a_user_lists_their_signed_in_devices_and_sees_which_one_is_this(): void
    {
        $this->registerShop(phone: self::OWNER_PHONE);
        $laptop = $this->signIn(self::OWNER_PHONE, 'password', 'Chrome · Windows');
        $phone = $this->signIn(self::OWNER_PHONE, 'password', 'Safari · iPhone');

        $sessions = collect($this->as($laptop)->getJson('/api/v1/account/sessions')->assertOk()->json('data'))->keyBy('id');

        $this->assertCount(2, $sessions);
        $this->assertTrue($sessions[$this->tokenId($laptop)]['current']);
        $this->assertFalse($sessions[$this->tokenId($phone)]['current']);
        $this->assertSame('Safari · iPhone', $sessions[$this->tokenId($phone)]['device_name']);
        $this->assertSame('Test browser on Safari · iPhone', $sessions[$this->tokenId($phone)]['user_agent']);
        $this->assertNotNull($sessions[$this->tokenId($laptop)]['last_used_at']);
    }

    public function test_revoking_a_device_signs_it_out_immediately(): void
    {
        $this->registerShop(phone: self::OWNER_PHONE);
        $laptop = $this->signIn(self::OWNER_PHONE, 'password', 'laptop');
        $phone = $this->signIn(self::OWNER_PHONE, 'password', 'phone');

        $this->as($phone)->getJson('/api/v1/auth/me')->assertOk();
        $this->as($laptop)->deleteJson('/api/v1/account/sessions/'.$this->tokenId($phone))->assertNoContent();

        $this->as($phone)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as($laptop)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertContains('auth.session_revoked', array_column($this->as($laptop)->getJson('/api/v1/audit-log')->json('data'), 'action'));
    }

    public function test_log_out_everywhere_else_keeps_this_device(): void
    {
        $this->registerShop(phone: self::OWNER_PHONE);
        $a = $this->signIn(self::OWNER_PHONE, 'password', 'a');
        $b = $this->signIn(self::OWNER_PHONE, 'password', 'b');
        $c = $this->signIn(self::OWNER_PHONE, 'password', 'c');

        $this->as($b)->deleteJson('/api/v1/account/sessions')->assertOk()->assertJsonPath('data.revoked', 2);

        $this->as($a)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as($c)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as($b)->getJson('/api/v1/account/sessions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_user_cannot_revoke_someone_elses_session(): void
    {
        $owner = $this->registerShop(phone: self::OWNER_PHONE);
        $ownerToken = $this->signIn(self::OWNER_PHONE, 'password', 'owner');
        [, $cashierToken] = $this->employeeOf($owner);
        $this->registerShop(phone: '+201055550003');
        $strangerToken = $this->signIn('+201055550003', 'password', 'stranger');

        $this->as($cashierToken)->deleteJson('/api/v1/account/sessions/'.$this->tokenId($ownerToken))->assertNotFound();
        $this->as($strangerToken)->deleteJson('/api/v1/account/sessions/'.$this->tokenId($ownerToken))->assertNotFound();

        $this->as($ownerToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_the_owner_sees_and_ends_an_employees_sessions(): void
    {
        $owner = $this->registerShop(phone: self::OWNER_PHONE);
        $ownerToken = $this->signIn(self::OWNER_PHONE, 'password', 'owner');
        [$cashier, $cashierToken] = $this->employeeOf($owner);

        $sessions = $this->as($ownerToken)->getJson("/api/v1/users/{$cashier->id}/sessions")->assertOk()->json('data');
        $this->assertSame(['Cashier PC'], array_column($sessions, 'device_name'));

        $this->as($ownerToken)->deleteJson("/api/v1/users/{$cashier->id}/sessions/{$sessions[0]['id']}")->assertNoContent();
        $this->as($cashierToken)->getJson('/api/v1/auth/me')->assertUnauthorized();

        // A session of another user isn't reachable through this employee.
        $this->as($ownerToken)->deleteJson("/api/v1/users/{$cashier->id}/sessions/".$this->tokenId($ownerToken))->assertNotFound();

        $again = $this->signIn('01122223333', 'secret-pass', 'Cashier PC');
        $this->as($ownerToken)->deleteJson("/api/v1/users/{$cashier->id}/sessions")->assertOk()->assertJsonPath('data.revoked', 1);
        $this->as($again)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $actions = array_column($this->as($ownerToken)->getJson('/api/v1/audit-log')->json('data'), 'action');
        $this->assertContains('users.session_revoked', $actions);
        $this->assertContains('users.sessions_revoked', $actions);
    }

    public function test_only_the_owner_of_the_same_shop_manages_staff_sessions(): void
    {
        $owner = $this->registerShop(ShopType::AccessoriesAndRepair, self::OWNER_PHONE);
        [$cashier] = $this->employeeOf($owner);
        [, $managerToken] = $this->employeeOf($owner, 'manager', '01144445555');

        $this->as($managerToken)->getJson("/api/v1/users/{$cashier->id}/sessions")->assertForbidden();
        $this->as($managerToken)->deleteJson("/api/v1/users/{$cashier->id}/sessions")->assertForbidden();

        $otherOwner = $this->registerShop();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($otherOwner);
        $this->getJson("/api/v1/users/{$cashier->id}/sessions")->assertNotFound();
    }

    public function test_the_owner_can_turn_off_an_employees_two_factor_but_not_their_own(): void
    {
        $owner = $this->registerShop(phone: self::OWNER_PHONE);
        [$cashier, $cashierToken] = $this->employeeOf($owner);
        $cashier->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        $ownerToken = $this->signIn(self::OWNER_PHONE, 'password', 'owner');
        $this->as($ownerToken)->deleteJson("/api/v1/users/{$owner->id}/two-factor")->assertUnprocessable();
        $this->as($ownerToken)->deleteJson("/api/v1/users/{$cashier->id}/two-factor")->assertNoContent();

        $this->assertFalse($cashier->refresh()->hasTwoFactor());
        $this->as($cashierToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_anyone_changes_their_own_password_and_other_devices_sign_out(): void
    {
        $owner = $this->registerShop();
        [, $pc] = $this->employeeOf($owner);
        $phone = $this->signIn('01122223333', 'secret-pass', 'Phone');

        $this->as($pc)->putJson('/api/v1/account/password', ['current_password' => 'wrong', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertUnprocessable()->assertJsonPath('code', 'wrong_password');
        $this->as($pc)->putJson('/api/v1/account/password', ['current_password' => 'secret-pass', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as($pc)->putJson('/api/v1/account/password', ['current_password' => 'secret-pass', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertOk()->assertJsonPath('data.signed_out_devices', 1);

        $this->as($phone)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as($pc)->getJson('/api/v1/auth/me')->assertOk();
        $this->signIn('01122223333', 'new-secret-1', 'Laptop');
    }
}
