<?php

namespace Tests\Feature\Identity;

use App\Modules\Billing\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Platform admins close self sign-up: the form gives way to WhatsApp and registering is refused. */
class SignupGateTest extends TestCase
{
    use RefreshDatabase;

    private function register()
    {
        return $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل النور',
            'shop_types' => ['accessories'],
            'owner_name' => 'أحمد',
            'phone' => '01012345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    public function test_admins_close_and_reopen_sign_up(): void
    {
        $admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
        $this->getJson('/api/v1/public/signup')->assertOk()->assertJsonPath('data.open', true);

        Sanctum::actingAs($admin, ['admin']);
        $this->putJson('/api/v1/admin/signup', ['open' => false])->assertUnprocessable()->assertJsonValidationErrors('whatsapp');
        $this->putJson('/api/v1/admin/signup', ['open' => false, 'whatsapp' => '01555440882'])->assertOk()
            ->assertJsonPath('data.open', false)->assertJsonPath('data.whatsapp', '+201555440882');
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'signup_settings_updated']);

        // Signed out: the page reads where to go, and registering is refused.
        $this->app['auth']->forgetGuards();
        $public = $this->getJson('/api/v1/public/signup')->assertOk()->json('data');
        $this->assertSame([false, '+201555440882'], [$public['open'], $public['whatsapp']]);
        $this->assertNotEmpty($public['message']);
        $this->register()->assertForbidden()->assertJsonPath('code', 'signup_closed');
        $this->assertDatabaseCount('tenants', 0);

        Sanctum::actingAs($admin, ['admin']);
        $this->putJson('/api/v1/admin/signup', ['open' => true, 'whatsapp' => '+201555440882', 'message' => 'كلّمنا'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->register()->assertCreated();
    }

    public function test_closed_by_default_without_a_saved_choice(): void
    {
        config(['services.signup.open' => false]);
        $this->getJson('/api/v1/public/signup')->assertOk()->assertJsonPath('data.open', false)->assertJsonPath('data.whatsapp', '+201555440882');
        $this->register()->assertForbidden()->assertJsonPath('code', 'signup_closed');
    }
}
