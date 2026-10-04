<?php

namespace Tests\Feature\Billing;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleTier;
use Tests\TestCase;

/** The website's pricing page reads the plans from the API, with no sign-in. */
class PublicPlansTest extends TestCase
{
    public function test_the_plans_are_public_and_match_the_config(): void
    {
        // A priced module whose screens aren't built yet (none in the codebase right now).
        app(ModuleRegistry::class)->register(new ModuleManifest(key: 'coming_soon', name: 'قريباً', tier: ModuleTier::Optional, available: false));
        config(['billing.modules.coming_soon' => 9900]);

        $res = $this->getJson('/api/v1/public/plans')->assertOk();

        $this->assertSame(config('billing.trial_days'), $res->json('data.trial_days'));
        $this->assertSame(14, $res->json('data.vat_percent'));
        $pro = collect($res->json('data.plans'))->firstWhere('key', 'pro');
        $this->assertSame(config('billing.plans.pro.monthly'), $pro['monthly']);
        $this->assertSame(config('billing.plans.pro.monthly') * config('billing.yearly_months'), $pro['yearly']);
        $this->assertTrue($pro['featured']);

        // Modules still being built are marked, so the site can say «قريباً».
        $repairs = collect($pro['modules'])->firstWhere('key', 'repairs');
        $this->assertTrue($repairs['available']);
        $this->assertTrue(collect($pro['modules'])->firstWhere('key', 'imports')['available']);
        $this->assertNotEmpty($res->json('data.modules'));
        $this->assertFalse(collect($res->json('data.modules'))->firstWhere('key', 'coming_soon')['available']);
    }
}
