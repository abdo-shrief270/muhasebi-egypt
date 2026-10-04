<?php

namespace Tests\Feature\Modules;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The platform admin shapes modules and feature switches for every shop, and one shop's modules. */
class PlatformModulesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs($this->admin, ['admin']);

        return $this;
    }

    private function asOwner(User $owner): static
    {
        Sanctum::actingAs($owner);

        return $this;
    }

    /** @return array<string, array<string, mixed>> */
    private function me(User $owner): array
    {
        $me = $this->asOwner($owner)->getJson('/api/v1/auth/me')->assertOk();

        return ['modules' => collect($me->json('data.modules'))->keyBy('key')->all(), 'features' => $me->json('data.features')];
    }

    public function test_the_catalog_and_the_shop_side_follow_the_admins_status_and_trial(): void
    {
        $catalog = $this->asAdmin()->getJson('/api/v1/admin/modules')->assertOk();
        $used = collect($catalog->json('data.modules'))->firstWhere('key', 'used_devices');
        $this->assertSame(['live', true, true, 14], [$used['status'], $used['trial_allowed'], $used['auto_trial'], $used['trial_days']]);
        $this->assertArrayHasKey('repair', $catalog->json('data.shop_types'));

        // No trials for used devices, installments not started on trial at registration, 30-day trials for services.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/used_devices', ['trial_allowed' => false])->assertOk();
        $this->asAdmin()->patchJson('/api/v1/admin/modules/installments', ['auto_trial' => false, 'name' => 'التقسيط والأقساط'])->assertOk();
        $this->asAdmin()->patchJson('/api/v1/admin/modules/supplier_returns', ['trial_days' => 30])->assertOk();
        $this->asAdmin()->patchJson('/api/v1/admin/modules/sales', ['status' => 'hidden'])->assertUnprocessable()->assertJsonPath('code', 'module_not_optional');

        $phones = $this->registerShop(ShopType::Phones);
        $me = $this->me($phones);
        $this->assertFalse($me['modules']['used_devices']['usable']);
        $this->assertFalse($me['modules']['installments']['usable']);
        $this->asOwner($phones)->postJson('/api/v1/modules/used_devices/trial')->assertStatus(409)->assertJsonPath('code', 'module_trial_closed');
        $modules = collect($this->asOwner($phones)->getJson('/api/v1/modules')->json('data'))->keyBy('key');
        $this->assertSame('التقسيط والأقساط', $modules['installments']['name']);
        $this->assertFalse($modules['used_devices']['trial_available']);
        $this->asOwner($phones)->postJson('/api/v1/modules/supplier_returns/trial')->assertOk();
        $this->assertSame(30, (int) round(now()->diffInDays($this->asOwner($phones)->getJson('/api/v1/modules')->collect('data')->firstWhere('key', 'supplier_returns')['trial_ends_at'])));

        // Back to what the module declares.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/used_devices', ['trial_allowed' => null])->assertOk();
        $this->asOwner($phones)->postJson('/api/v1/modules/used_devices/trial')->assertOk();
    }

    public function test_free_hidden_and_coming_soon_modules(): void
    {
        $shop = $this->registerShop(ShopType::Phones);

        // Free for every shop: usable with no subscription, the owner may still hide it.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/installments', ['status' => 'free'])->assertOk();
        $this->assertTrue($this->me($shop)['modules']['installments']['usable']);
        $this->asOwner($shop)->getJson('/api/v1/installments/due')->assertOk();
        $this->asOwner($shop)->postJson('/api/v1/modules/installments/disable')->assertOk();
        $this->assertFalse($this->me($shop)['modules']['installments']['usable']);
        $this->asOwner($shop)->postJson('/api/v1/modules/installments/enable')->assertOk();
        $this->assertTrue($this->me($shop)['modules']['installments']['usable']);
        $this->assertTrue(collect($this->getJson('/api/v1/public/plans')->json('data.modules'))->firstWhere('key', 'installments')['free'] ?? true);

        // Coming soon: listed as «قريباً», off. Hidden: not listed at all.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/installments', ['status' => 'coming_soon'])->assertOk();
        $this->assertFalse($this->me($shop)['modules']['installments']['usable']);
        $this->assertFalse($this->asOwner($shop)->getJson('/api/v1/modules')->collect('data')->firstWhere('key', 'installments')['available']);
        $this->asAdmin()->patchJson('/api/v1/admin/modules/installments', ['status' => 'hidden'])->assertOk();
        $this->assertNull($this->asOwner($shop)->getJson('/api/v1/modules')->collect('data')->firstWhere('key', 'installments'));
        $this->assertNull(collect($this->getJson('/api/v1/public/plans')->json('data.modules'))->firstWhere('key', 'installments'));
        $this->asOwner($shop)->getJson('/api/v1/installments/due')->assertForbidden();
    }

    public function test_feature_switches_forced_or_with_a_new_default(): void
    {
        $shop = $this->registerShop();
        $this->asOwner($shop)->putJson('/api/v1/features/catalog.labels', ['enabled' => false])->assertOk();
        $this->assertFalse($this->me($shop)['features']['catalog.labels']);

        // On for every shop: the owner's choice no longer counts and can't be changed.
        $this->asAdmin()->patchJson('/api/v1/admin/features/catalog.labels', ['mode' => 'on'])->assertOk();
        $this->assertTrue($this->me($shop)['features']['catalog.labels']);
        $this->asOwner($shop)->putJson('/api/v1/features/catalog.labels', ['enabled' => false])->assertStatus(409)->assertJsonPath('code', 'feature_locked');
        $labels = collect($this->asOwner($shop)->getJson('/api/v1/features')->json('data'))->firstWhere('module', 'catalog')['features'];
        $this->assertTrue(collect($labels)->firstWhere('key', 'catalog.labels')['locked']);

        // Off for every shop: gone from the page and refused.
        $this->asAdmin()->patchJson('/api/v1/admin/features/catalog.excel_import', ['mode' => 'off'])->assertOk();
        $this->assertFalse($this->me($shop)['features']['catalog.excel_import']);
        $catalog = collect(collect($this->asOwner($shop)->getJson('/api/v1/features')->json('data'))->firstWhere('module', 'catalog')['features']);
        $this->assertNull($catalog->firstWhere('key', 'catalog.excel_import'));
        $this->asOwner($shop)->putJson('/api/v1/features/catalog.excel_import', ['enabled' => true])->assertNotFound();

        // A new default: shops that never chose follow it.
        $this->asAdmin()->patchJson('/api/v1/admin/features/catalog.bulk_prices', ['default' => false])->assertOk();
        $this->assertFalse($this->me($shop)['features']['catalog.bulk_prices']);
        $feature = collect(collect($this->asAdmin()->getJson('/api/v1/admin/modules')->json('data.modules'))->firstWhere('key', 'catalog')['features'])->keyBy('key');
        $this->assertSame(['on', 'off', 'shop', false, true], [$feature['catalog.labels']['mode'], $feature['catalog.excel_import']['mode'], $feature['catalog.bulk_prices']['mode'], $feature['catalog.bulk_prices']['default'], $feature['catalog.bulk_prices']['declared_default']]);

        // Back to the shop's own choice.
        $this->asAdmin()->patchJson('/api/v1/admin/features/catalog.labels', ['mode' => 'shop'])->assertOk();
        $this->assertFalse($this->me($shop)['features']['catalog.labels']);
    }

    public function test_one_shops_modules(): void
    {
        $shop = $this->registerShop();
        $url = "/api/v1/admin/shops/{$shop->tenant_id}/modules";
        $this->assertFalse(collect($this->asAdmin()->getJson($url)->assertOk()->json('data'))->firstWhere('key', 'installments')['usable']);

        $this->asAdmin()->postJson("{$url}/installments", ['action' => 'open'])->assertOk();
        $this->assertTrue($this->me($shop)['modules']['installments']['usable']);
        $this->asAdmin()->postJson("{$url}/installments", ['action' => 'close'])->assertOk();
        $this->assertFalse($this->me($shop)['modules']['installments']['usable']);

        // A fresh trial even after the shop used its own.
        $this->asOwner($shop)->postJson('/api/v1/modules/supplier_returns/trial')->assertOk();
        $this->asAdmin()->postJson("{$url}/supplier_returns", ['action' => 'close'])->assertOk();
        $this->assertFalse($this->me($shop)['modules']['supplier_returns']['usable']);
        $this->asOwner($shop)->postJson('/api/v1/modules/supplier_returns/trial')->assertUnprocessable()->assertJsonPath('code', 'module_trial_used');
        $this->asAdmin()->postJson("{$url}/supplier_returns", ['action' => 'trial'])->assertOk();
        $this->assertTrue($this->me($shop)['modules']['supplier_returns']['usable']);

        $this->asAdmin()->postJson("{$url}/sales", ['action' => 'open'])->assertUnprocessable()->assertJsonPath('code', 'module_not_optional');
    }
}
