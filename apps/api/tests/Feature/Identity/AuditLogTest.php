<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\ShopType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_sensitive_actions_show_up_in_the_shops_audit_log(): void
    {
        $this->actingAsOwnerOf(ShopType::Accessories);
        $this->postJson('/api/v1/modules/repairs/trial')->assertOk();
        $this->postJson('/api/v1/roles', ['name' => 'مساعد', 'permissions' => ['sales.sell']])->assertCreated();

        $entries = $this->getJson('/api/v1/audit-log')->assertOk()->json('data');
        $actions = array_column($entries, 'action');

        $this->assertSame('roles.created', $actions[0], 'newest first');
        $this->assertContains('modules.trial', $actions);
        $this->assertContains('shop.registered', $actions);
        $this->assertSame('المالك', $entries[0]['user_name']);
        $this->assertStringContainsString('مساعد', $entries[0]['description']);
    }

    public function test_a_shop_only_sees_its_own_audit_log(): void
    {
        $this->registerShop();
        $this->actingAsOwnerOf();

        $this->assertCount(1, $this->getJson('/api/v1/audit-log')->json('data'));
    }
}
