<?php

namespace Tests\Feature\Onboarding;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Onboarding\Contracts\SetupProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @return array<string, bool> step key => done */
    private function steps(): array
    {
        return collect($this->getJson('/api/v1/onboarding')->assertOk()->json('data.steps'))->pluck('done', 'key')->all();
    }

    public function test_a_new_shop_starts_with_nothing_done(): void
    {
        $this->actingAsOwnerOf(ShopType::Accessories);

        $data = $this->getJson('/api/v1/onboarding')->assertOk()->json('data');
        $this->assertTrue($data['visible']);
        $this->assertSame(0, $data['done']);
        $keys = array_column($data['steps'], 'key');
        // An accessories shop doesn't use repairs; Excel import is on by default.
        $this->assertSame(['shop_info', 'products', 'market_location', 'stock', 'staff', 'shift', 'first_sale', 'two_factor'], $keys);
        $this->assertSame('/products/import', $data['steps'][1]['to']);
    }

    public function test_steps_are_ticked_from_the_shops_data(): void
    {
        $this->openShopWithStock();
        $this->assertContains('first_repair', array_keys($this->steps()));

        $done = $this->steps();
        $this->assertTrue($done['products']);
        $this->assertTrue($done['stock']);
        $this->assertFalse($done['shop_info']);
        $this->assertFalse($done['shift']);
        $this->assertFalse($done['first_sale']);
        $this->assertFalse($done['staff']);

        $this->inShop(fn () => Branch::query()->where('is_main', true)->update(['address' => 'شارع الجمهورية']));
        $this->openShift();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();
        $this->staff('cashier');

        $done = $this->steps();
        foreach (['shop_info', 'shift', 'first_sale', 'staff'] as $key) {
            $this->assertTrue($done[$key], $key);
        }
        $this->assertFalse($done['first_repair']);
        $this->assertFalse($done['two_factor']);
        $this->assertFalse($done['market_location']);

        $progress = app(SetupProgress::class)->progress([$this->owner->tenant_id])[$this->owner->tenant_id];
        $this->assertSame(6, $progress['done']);
        $this->assertSame(9, $progress['total']);
        $this->assertSame(['حدد مكان محلك', 'استلم أول جهاز صيانة', 'فعّل التحقق بخطوتين'], $progress['missing']);
    }

    public function test_the_card_is_for_owners_and_managers_with_their_own_steps(): void
    {
        $this->openShopWithStock();
        $cashier = $this->staff('cashier');
        $manager = $this->staff('manager');

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/onboarding')->assertOk()->assertJsonPath('data.visible', false)->assertJsonPath('data.steps', []);

        Sanctum::actingAs($manager);
        $keys = array_keys($this->steps());
        $this->assertContains('staff', $keys);
        $this->assertNotContains('two_factor', $keys); // the owner's own account
    }

    public function test_folding_and_hiding_is_remembered_per_user(): void
    {
        $owner = $this->actingAsOwnerOf();
        $this->patchJson('/api/v1/onboarding', ['collapsed' => true])->assertOk()->assertJsonPath('data.collapsed', true)->assertJsonPath('data.visible', true);
        $this->getJson('/api/v1/onboarding')->assertJsonPath('data.collapsed', true);

        $this->patchJson('/api/v1/onboarding', ['dismissed' => true])->assertOk()->assertJsonPath('data.visible', false)->assertJsonPath('data.dismissed', true);
        $this->patchJson('/api/v1/onboarding', ['dismissed' => false])->assertOk()->assertJsonPath('data.visible', true);

        // Another shop's owner has their own card.
        $this->actingAsOwnerOf();
        $this->getJson('/api/v1/onboarding')->assertJsonPath('data.collapsed', false);
        $this->assertNotNull($owner);
    }
}
