<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Every sign-in stamps the user's last_login_at (devices are deleted on logout, so they can't tell). */
class LastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_in_records_when(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل النور', 'shop_types' => ['accessories'], 'owner_name' => 'أحمد', 'phone' => '01012345678',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated();
        $user = User::query()->where('phone', '+201012345678')->firstOrFail();
        $this->assertNotNull($user->last_login_at, 'registering signs the owner in');

        Carbon::setTestNow(now()->addDay());
        $token = $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'password123', 'device_name' => 'test'])->assertOk()->json('token');
        $this->assertTrue($user->fresh()->last_login_at->equalTo(now()->startOfSecond()) || $user->fresh()->last_login_at->diffInSeconds(now(), true) < 2);

        // It survives logging out, and the staff list shows it.
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertSuccessful();
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'password123', 'device_name' => 'test'])->json('token');
        $this->withToken($token)->getJson('/api/v1/users')->assertOk()->assertJsonPath('data.0.last_login_at', $user->fresh()->last_login_at->toIso8601String());
    }
}
