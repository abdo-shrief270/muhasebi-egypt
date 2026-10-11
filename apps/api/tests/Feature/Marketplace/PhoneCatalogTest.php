<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Support\PhoneList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** «كتالوج الموبايلات»: the platform admins keep the reference list of phones. */
class PhoneCatalogTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']), ['admin']);
    }

    public function test_the_list_starts_from_the_json_file_and_filters(): void
    {
        $count = count(json_decode((string) file_get_contents(database_path('data/market/phones.json')), true)['phones']);
        $this->assertSame($count, DB::table('market_phones')->count());
        $this->assertSame(0, PhoneList::import(), 'importing again adds nothing');

        $all = $this->getJson('/api/v1/admin/phones')->assertOk();
        $this->assertSame($count, $all->json('data.total'));
        $this->assertGreaterThan(0, $all->json('data.statuses.review'));
        $this->assertArrayHasKey('Samsung', $all->json('data.brands'));

        $found = $this->getJson('/api/v1/admin/phones?q=ايفون 15')->assertOk();
        $this->assertNotEmpty($found->json('data.phones'));
        $this->assertTrue(collect($found->json('data.phones'))->every(fn ($p) => $p['brand'] === 'Apple'));

        $review = $this->getJson('/api/v1/admin/phones?status=review&brand=Samsung')->assertOk();
        $this->assertTrue(collect($review->json('data.phones'))->every(fn ($p) => $p['status'] === 'review' && $p['brand'] === 'Samsung'));
    }

    public function test_admins_add_change_and_delete_a_phone(): void
    {
        $phone = $this->postJson('/api/v1/admin/phones', [
            'brand' => 'Samsung', 'series' => 'Galaxy A', 'model' => 'Galaxy A99 Test', 'aliases' => ['سامسونج A99', ' ', 'سامسونج A99'],
            'year' => 2026, 'storage_gb' => [256, 128], 'network' => '5G', 'status' => 'review',
        ])->assertCreated()->json('data');
        $this->assertSame(['سامسونج A99'], $phone['aliases']);
        $this->assertSame([128, 256], $phone['storage_gb']);
        $this->assertSame('samsung-galaxy-a99-test', $phone['slug']);

        $this->postJson('/api/v1/admin/phones', ['brand' => 'samsung', 'model' => 'galaxy a99 test'])
            ->assertUnprocessable()->assertJsonPath('code', 'phone_exists');

        $this->patchJson("/api/v1/admin/phones/{$phone['id']}", ['status' => 'published', 'notes' => 'اتأكدنا'])
            ->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.updated_by_name', 'الإدارة');
        $this->assertContains($phone['id'], array_column(app(PhoneCatalog::class)->published(), 'id'));

        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'phone_changed', 'subject_id' => $phone['id']]);
        $this->assertSame(DB::table('market_phones')->where('status', 'review')->count(), $this->getJson('/api/v1/admin/badges')->json('data.phones'));

        $this->deleteJson("/api/v1/admin/phones/{$phone['id']}")->assertOk();
        $this->deleteJson("/api/v1/admin/phones/{$phone['id']}")->assertNotFound();
        $this->patchJson('/api/v1/admin/phones/'.$phone['id'], ['status' => 'hidden'])->assertNotFound();
    }

    public function test_shops_cant_reach_it(): void
    {
        $this->actingAsOwnerOf();
        $this->getJson('/api/v1/admin/phones')->assertForbidden();
    }
}
