<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Support\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private const PHONE = '+201055550001';

    /** Turns 2FA on through the API; returns [secret, recovery codes]. */
    private function enable(User $user): array
    {
        Sanctum::actingAs($user);
        $secret = $this->postJson('/api/v1/account/two-factor/setup', ['password' => 'password'])->assertOk()->json('data.secret');
        $codes = $this->postJson('/api/v1/account/two-factor/confirm', ['code' => Totp::code($secret)])->assertOk()->json('data.recovery_codes');
        $this->app['auth']->forgetGuards();

        return [$secret, $codes];
    }

    private function login(): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['phone' => self::PHONE, 'password' => 'password', 'device_name' => 'Chrome · Windows']);
    }

    /** A code the app would show in the next step (the current one was used to confirm). */
    private function nextCode(string $secret, int $ahead = 1): string
    {
        return Totp::code($secret, Totp::step() + $ahead);
    }

    public function test_a_user_enables_two_factor_with_a_code_and_gets_recovery_codes(): void
    {
        $owner = $this->actingAsOwnerOf();

        $this->postJson('/api/v1/account/two-factor/setup', ['password' => 'wrong'])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_password');

        $setup = $this->postJson('/api/v1/account/two-factor/setup', ['password' => 'password'])->assertOk()->json('data');
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_url']);
        $this->assertFalse($this->getJson('/api/v1/account/two-factor')->json('data.enabled'), 'not on until confirmed');

        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('code', 'two_factor_invalid_code');

        $codes = $this->postJson('/api/v1/account/two-factor/confirm', ['code' => Totp::code($setup['secret'])])
            ->assertOk()->json('data.recovery_codes');
        $this->assertCount(8, $codes);

        $this->getJson('/api/v1/account/two-factor')->assertJsonPath('data.enabled', true)->assertJsonPath('data.recovery_codes_left', 8);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.user.two_factor_enabled', true);

        // At rest: the secret is encrypted, the recovery codes only hashes.
        $row = DB::table('users')->where('id', $owner->id)->first();
        $this->assertStringNotContainsString($setup['secret'], $row->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], $row->two_factor_recovery_codes);
        $this->assertSame($setup['secret'], $owner->refresh()->two_factor_secret);

        $this->assertContains('auth.two_factor_enabled', array_column($this->getJson('/api/v1/audit-log')->json('data'), 'action'));
    }

    public function test_login_with_two_factor_returns_a_challenge_then_a_token_for_the_right_code(): void
    {
        $owner = $this->registerShop(phone: self::PHONE);
        [$secret] = $this->enable($owner);

        $challenge = $this->login()->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonMissingPath('token')
            ->json('challenge');

        $token = $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $this->nextCode($secret)])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.id', $owner->id);

        // The challenge is used up.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $this->nextCode($secret)])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge');
    }

    public function test_a_wrong_code_is_refused_and_too_many_end_the_challenge(): void
    {
        $owner = $this->registerShop(phone: self::PHONE);
        [$secret] = $this->enable($owner);
        $challenge = $this->login()->json('challenge');

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        // A code from a few minutes ago is too old.
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret, Totp::step() - 5)])
            ->assertUnprocessable();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => 'nope-nope']);
        }

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $this->nextCode($secret)])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge');
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $owner->id)->count());
    }

    public function test_the_same_code_signs_in_only_once(): void
    {
        $owner = $this->registerShop(phone: self::PHONE);
        [$secret] = $this->enable($owner);
        $code = $this->nextCode($secret);

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $this->login()->json('challenge'), 'code' => $code])->assertOk();
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $this->login()->json('challenge'), 'code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_a_recovery_code_works_once(): void
    {
        $owner = $this->registerShop(phone: self::PHONE);
        [, $codes] = $this->enable($owner);

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $this->login()->json('challenge'), 'code' => strtoupper($codes[2])])
            ->assertOk()->assertJsonPath('recovery_codes_left', 7)->assertJsonStructure(['token']);

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $this->login()->json('challenge'), 'code' => $codes[2]])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_the_challenge_endpoint_is_rate_limited(): void
    {
        $this->registerShop(phone: self::PHONE);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/two-factor', ['challenge' => str_repeat('x', 64), 'code' => '123456'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => str_repeat('x', 64), 'code' => '123456'])->assertTooManyRequests();
    }

    public function test_disabling_needs_the_password_and_a_code(): void
    {
        $owner = $this->registerShop(phone: self::PHONE);
        [$secret, $codes] = $this->enable($owner);
        Sanctum::actingAs($owner->refresh());

        $this->deleteJson('/api/v1/account/two-factor', ['password' => 'wrong', 'code' => $this->nextCode($secret)])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_password');
        $this->deleteJson('/api/v1/account/two-factor', ['password' => 'password', 'code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('code', 'two_factor_invalid_code');
        $this->deleteJson('/api/v1/account/two-factor', ['password' => 'password', 'code' => $codes[0]])->assertNoContent();

        $this->getJson('/api/v1/account/two-factor')->assertJsonPath('data.enabled', false);
        $this->assertNull(DB::table('users')->where('id', $owner->id)->value('two_factor_secret'));

        $this->app['auth']->forgetGuards();
        $this->login()->assertOk()->assertJsonStructure(['token'])->assertJsonMissingPath('two_factor');
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        $owner = $this->registerShop(phone: self::PHONE);
        [, $old] = $this->enable($owner);
        Sanctum::actingAs($owner->refresh());

        $new = $this->postJson('/api/v1/account/two-factor/recovery-codes', ['password' => 'password'])->assertOk()->json('data.recovery_codes');
        $this->assertCount(8, $new);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $this->login()->json('challenge'), 'code' => $old[0]])->assertUnprocessable();
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $this->login()->json('challenge'), 'code' => $new[0]])->assertOk();
    }
}
