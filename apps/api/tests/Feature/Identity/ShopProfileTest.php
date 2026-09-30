<?php

namespace Tests\Feature\Identity;

use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** The owner's «بيانات المحل والإيصال»: name, phone and what receipts print. */
class ShopProfileTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openShopWithStock();
    }

    public function test_the_owner_sets_the_shop_name_phone_and_receipt_lines(): void
    {
        $this->getJson('/api/v1/shop/profile')->assertOk()
            ->assertJsonPath('data.receipt.footer', 'شكراً لزيارتك 🌷')
            ->assertJsonPath('data.receipt.tax_number', null);

        $this->putJson('/api/v1/shop/profile', [
            'name' => 'موبايلات النور',
            'phone' => '01011112222',
            'tax_number' => ' 123-456-789 ',
            'commercial_register' => '98765',
            'footer' => 'الضمان 14 يوم بالإيصال',
        ])->assertOk()
            ->assertJsonPath('data.name', 'موبايلات النور')
            ->assertJsonPath('data.phone', '+201011112222')
            ->assertJsonPath('data.receipt.tax_number', '123-456-789')
            ->assertJsonPath('data.receipt.footer', 'الضمان 14 يوم بالإيصال');

        // The session carries it, so every receipt prints it.
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.tenant.receipt.commercial_register', '98765');
        $this->assertTrue($this->inShop(fn () => AuditEntry::query()->where('action', 'shop.profile_updated')->exists()));

        // An empty footer falls back to the default line.
        $this->putJson('/api/v1/shop/profile', ['name' => 'موبايلات النور', 'phone' => '01011112222', 'footer' => ''])
            ->assertOk()->assertJsonPath('data.receipt.footer', 'شكراً لزيارتك 🌷')->assertJsonPath('data.receipt.tax_number', null);
    }

    public function test_the_public_receipt_prints_the_shop_lines(): void
    {
        $this->putJson('/api/v1/shop/profile', ['name' => 'موبايلات النور', 'phone' => '01011112222', 'tax_number' => '123456789', 'footer' => 'نورتنا'])->assertOk();
        $this->openShift();
        $token = $this->sell([['method' => 'cash', 'amount' => 45000]], ['id' => (string) Str::uuid7()])->assertCreated()->json('data.public_token');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/public/receipts/{$token}")->assertOk()
            ->assertJsonPath('data.shop.name', 'موبايلات النور')
            ->assertJsonPath('data.shop.receipt.tax_number', '123456789')
            ->assertJsonPath('data.shop.receipt.footer', 'نورتنا');
    }

    public function test_it_is_for_the_owner_only_and_validated(): void
    {
        $this->putJson('/api/v1/shop/profile', ['name' => '', 'phone' => '123', 'footer' => str_repeat('x', 201)])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'footer']);

        Sanctum::actingAs($this->staff('manager'));
        $this->getJson('/api/v1/shop/profile')->assertForbidden();
        $this->putJson('/api/v1/shop/profile', ['name' => 'x', 'phone' => '01011112222'])->assertForbidden();
    }
}
