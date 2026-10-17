<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\BillingCoupon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Test shops come off a live server for good; the other shops, and real customers, stay. */
class DeleteShopsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private const CONFIRM = 'Delete 1 shop(s) and everything above for good? There is no undo.';

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // The test shop: stock, a sale, a photo, feedback with a screenshot, a coupon and a payment request with its proof.
        $this->openShopWithStock();
        $this->openShift();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();
        $product = $this->getJson('/api/v1/products')->json('data.0.id');
        $this->post("/api/v1/products/{$product}/images", ['image' => UploadedFile::fake()->image('case.jpg', 800, 600)], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/v1/feedback', ['type' => 'problem', 'message' => 'تجربة', 'page' => '/', 'screenshot' => UploadedFile::fake()->image('s.png')], ['Accept' => 'application/json'])->assertCreated();
        BillingCoupon::query()->create(['code' => 'FOUNDERS', 'kind' => 'percent', 'value' => 50, 'months' => 3]);
        $this->postJson('/api/v1/billing/coupon', ['code' => 'FOUNDERS'])->assertOk();
        $this->post('/api/v1/billing/requests', ['plan' => 'repair', 'cycle' => 'monthly', 'reference' => 'IP-1', 'proof' => UploadedFile::fake()->image('t.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->owner->createToken('phone');

        // Another shop, connected to the test shop, with a photo of its own.
        $this->other = $this->registerShop(ShopType::Accessories);
        Sanctum::actingAs($this->other);
        $category = app(CurrentTenant::class)->runAs($this->other->tenant_id, fn () => Category::query()->value('id'));
        $kept = $this->postJson('/api/v1/products', ['name' => 'سماعة', 'category_id' => $category, 'variants' => [['price_retail' => 20000]]])->assertCreated()->json('data.id');
        $this->post("/api/v1/products/{$kept}/images", ['image' => UploadedFile::fake()->image('h.jpg', 800, 600)], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/v1/shop-connections', ['code' => $this->code($this->owner)])->assertCreated();
    }

    private function code(User $user): string
    {
        return $user->tenant()->firstOrFail()->code;
    }

    /** @return array<string, int> rows per table that point at the shop */
    private function rowsOf(string $tenantId): array
    {
        $columns = DB::select(<<<'SQL'
            select c.conrelid::regclass::text as "table", a.attname as "column"
            from pg_constraint c join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f' and c.confrelid = 'tenants'::regclass
            union
            select table_name, column_name from information_schema.columns where table_schema = current_schema() and column_name = 'tenant_id'
            SQL);
        $rows = [];
        foreach ($columns as $c) {
            if ($c->table !== 'platform_admin_actions' && ($n = DB::table($c->table)->where($c->column, $tenantId)->count()) > 0) {
                $rows["{$c->table}.{$c->column}"] = $n;
            }
        }

        return $rows;
    }

    public function test_it_lists_the_shops_and_changes_nothing_without_force(): void
    {
        $test = $this->code($this->owner);
        $this->artisan('shops:delete')->expectsOutputToContain($test)->expectsOutputToContain($this->code($this->other))->assertSuccessful();
        $this->artisan('shops:delete', ['codes' => ['NOPE42']])->expectsOutputToContain('No shop with the code: NOPE42')->assertFailed();

        $before = $this->rowsOf($this->owner->tenant_id);
        $this->artisan('shops:delete', ['codes' => [strtolower($test)]])
            ->expectsOutputToContain('sales')
            ->expectsOutputToContain('Nothing was deleted')
            ->assertSuccessful();
        // Answering «no» stops too.
        $this->artisan('shops:delete', ['codes' => [$test], '--force' => true])->expectsConfirmation(self::CONFIRM, 'no')->assertFailed();

        $this->assertSame($before, $this->rowsOf($this->owner->tenant_id));
        $this->assertDatabaseHas('tenants', ['id' => $this->owner->tenant_id]);
    }

    public function test_force_removes_every_trace_of_the_shop_and_nothing_of_the_others(): void
    {
        $test = $this->owner->tenant_id;
        $other = $this->other->tenant_id;
        $this->assertArrayHasKey('sales.tenant_id', $this->rowsOf($test));
        $this->assertSame(1, BillingCoupon::query()->value('redemptions'));
        $this->assertNotEmpty(Storage::disk('local')->allFiles("products/{$test}"));
        $this->assertNotEmpty(Storage::disk('local')->allFiles("feedback/{$test}"));
        $this->assertNotEmpty(Storage::disk('local')->allFiles("billing-proofs/{$test}"));
        $kept = $this->rowsOf($other);
        unset($kept['shop_connections.addressee_tenant_id'], $kept['shop_connections.requester_tenant_id']);

        $this->artisan('shops:delete', ['codes' => [$this->code($this->owner)], '--force' => true])
            ->expectsConfirmation(self::CONFIRM, 'yes')
            ->expectsOutputToContain('Deleted 1 shop(s)')
            ->assertSuccessful();

        // Every row, sign-in and file of the test shop is gone.
        $this->assertSame([], $this->rowsOf($test));
        $this->assertDatabaseMissing('tenants', ['id' => $test]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->owner->id)->count());
        $this->assertSame([], Storage::disk('local')->allFiles("products/{$test}"));
        $this->assertSame([], Storage::disk('local')->allFiles("feedback/{$test}"));
        $this->assertSame([], Storage::disk('local')->allFiles("billing-proofs/{$test}"));
        // Its use of the coupon no longer counts; the admins' log says it happened.
        $this->assertSame(0, BillingCoupon::query()->value('redemptions'));
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'shop_deleted', 'tenant_id' => $test]);

        // The other shop keeps everything but the link to the deleted one.
        $this->assertDatabaseHas('tenants', ['id' => $other]);
        $this->assertSame($kept, $this->rowsOf($other));
        $this->assertNotEmpty(Storage::disk('local')->allFiles("products/{$other}"));
    }

    public function test_a_shop_that_paid_needs_include_paid_and_all_keeps_the_exceptions(): void
    {
        BillingInvoice::withoutTenancy()->create([
            'tenant_id' => $this->other->tenant_id, 'number' => 1, 'plan' => 'accessories', 'cycle' => 'monthly', 'months' => 1,
            'lines' => [], 'total' => 34900, 'vat' => 4286, 'period_start' => now(), 'period_end' => now()->addMonth(), 'method' => 'instapay', 'paid_at' => now(),
        ]);
        $paying = $this->code($this->other);

        $this->artisan('shops:delete', ['codes' => [$paying], '--force' => true])->expectsOutputToContain("These shops paid real money: {$paying}")->assertFailed();
        $this->artisan('shops:delete', ['codes' => [$paying], '--include-paid' => true])->expectsOutputToContain('Nothing was deleted')->assertSuccessful();

        // --all --except: every shop but the one kept.
        $this->artisan('shops:delete', ['--all' => true, '--except' => [$paying], '--force' => true])
            ->expectsConfirmation(self::CONFIRM, 'yes')
            ->assertSuccessful();
        $this->assertDatabaseMissing('tenants', ['id' => $this->owner->tenant_id]);
        $this->assertDatabaseHas('tenants', ['id' => $this->other->tenant_id]);
    }
}
