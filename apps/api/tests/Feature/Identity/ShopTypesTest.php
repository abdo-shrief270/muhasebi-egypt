<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTypesTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل النور',
            'owner_name' => 'أحمد',
            'phone' => '010'.random_int(10000000, 99999999),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ]);
    }

    private function signIn(array $types): string
    {
        $token = $this->register(['shop_types' => $types])->assertCreated()->json('token');
        $this->withToken($token);

        return $token;
    }

    /** @return array<string, array<string, mixed>> key => module */
    private function modules(): array
    {
        return collect($this->getJson('/api/v1/modules')->assertOk()->json('data'))->keyBy('key')->all();
    }

    public function test_the_registration_form_lists_the_types_with_their_modules(): void
    {
        $types = collect($this->getJson('/api/v1/shop-types')->assertOk()->json('data'))->keyBy('value');

        $this->assertSame(['accessories', 'repair', 'phones', 'wholesale', 'importer'], $types->keys()->all());
        $repairs = fn (string $type) => collect($types[$type]['modules'])->firstWhere('key', 'repairs');
        $this->assertTrue($repairs('repair')['trial']);
        $this->assertFalse($repairs('accessories')['trial'], 'accessories shops see repairs (to send devices to a partner) but don\'t start on it');
        $this->assertNull($repairs('wholesale'));
    }

    public function test_a_shop_of_several_types_sees_and_tries_the_modules_made_for_them(): void
    {
        $this->signIn(['accessories', 'repair']);

        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.tenant');
        $this->assertSame([['accessories', 'repair'], 'إكسسوارات + صيانة'], [$me['shop_types'], $me['shop_type_label']]);

        $modules = $this->modules();
        $this->assertSame('trial', $modules['repairs']['state']);
        $this->assertSame('trial', $modules['shop_orders']['state']);
        $this->assertArrayHasKey('sales', $modules, 'core modules always');
        $this->assertArrayHasKey('multi_branch', $modules, 'modules for every shop');
        $this->assertArrayNotHasKey('imports', $modules, 'for importers only');
        $this->assertArrayNotHasKey('installments', $modules, 'for phone and wholesale shops');
    }

    public function test_a_wholesaler_does_not_see_repairs_until_it_says_it_repairs(): void
    {
        $this->signIn(['wholesale']);
        $this->assertArrayNotHasKey('repairs', $this->modules());

        $this->putJson('/api/v1/shop/types', ['shop_types' => ['wholesale', 'repair']])
            ->assertOk()->assertJsonPath('data.shop_types', ['wholesale', 'repair']);

        $repairs = $this->modules()['repairs'];
        $this->assertSame([false, true], [$repairs['usable'], $repairs['trial_available']], 'shown, to try when the owner wants');

        // Dropping the type again keeps a module the shop already uses.
        $this->postJson('/api/v1/modules/repairs/trial')->assertOk();
        $this->putJson('/api/v1/shop/types', ['shop_types' => ['wholesale']])->assertOk();
        $this->assertArrayHasKey('repairs', $this->modules());
    }

    public function test_an_accessories_shop_sees_repairs_without_starting_its_trial(): void
    {
        $this->signIn(['accessories']);
        $repairs = $this->modules()['repairs'];
        $this->assertSame([false, true], [$repairs['usable'], $repairs['trial_available']]);
    }

    public function test_types_are_validated_and_the_old_single_type_still_works(): void
    {
        $this->register(['shop_types' => []])->assertUnprocessable()->assertJsonValidationErrors('shop_types');
        $this->register(['shop_types' => ['accessories_repair']])->assertUnprocessable();
        $this->register(['shop_types' => ['repair', 'repair']])->assertUnprocessable();

        $this->withToken($this->register(['shop_type' => 'accessories_repair'])->assertCreated()->json('token'));
        $this->assertSame(['accessories', 'repair'], $this->getJson('/api/v1/auth/me')->json('data.tenant.shop_types'));

        $this->putJson('/api/v1/shop/types', ['shop_types' => []])->assertUnprocessable();
    }
}
