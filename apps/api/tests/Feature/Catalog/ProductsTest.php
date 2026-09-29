<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\GenerateBarcodesAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Support\DefaultCatalog;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->newShop();
        Sanctum::actingAs($this->owner);
    }

    /** Registers a shop and delivers its events, which seeds its starter catalog. */
    private function newShop(): User
    {
        $owner = $this->registerShop(ShopType::AccessoriesAndRepair);
        app(EventRelay::class)->publishPending();

        return $owner;
    }

    private function inShop(User $user, callable $callback): mixed
    {
        return app(CurrentTenant::class)->runAs($user->tenant_id, $callback);
    }

    private function categoryId(string $name = 'سكرينات حماية', ?User $of = null): int
    {
        return $this->inShop($of ?? $this->owner, fn () => Category::query()->where('name', $name)->value('id'));
    }

    private function modelId(string $name, ?User $of = null): int
    {
        return $this->inShop($of ?? $this->owner, fn () => DeviceModel::query()->where('name', $name)->value('id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'سكرينة 9D',
            'category_id' => $this->categoryId(),
            'device_model_ids' => [$this->modelId('iPhone 13'), $this->modelId('iPhone 14')],
            'variants' => [
                ['name' => 'شفاف', 'barcode' => '6221234567890', 'price_retail' => 15000, 'price_wholesale' => 9000],
                ['name' => 'مطفي', 'price_retail' => 17500],
            ],
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createProduct(array $overrides = []): array
    {
        return $this->postJson('/api/v1/products', $this->payload($overrides))->assertCreated()->json('data');
    }

    /**
     * @return list<string>
     */
    private function search(string $query): array
    {
        return array_column($this->getJson('/api/v1/products?'.$query)->assertOk()->json('data'), 'name');
    }

    private function staffWithRole(string $roleKey): User
    {
        $roleId = $this->inShop($this->owner, fn () => Role::query()->where('key', $roleKey)->value('id'));

        return User::create([
            'tenant_id' => $this->owner->tenant_id,
            'name' => $roleKey,
            'phone' => '+2011'.random_int(10000000, 99999999),
            'password' => 'password',
            'role_id' => $roleId,
        ]);
    }

    public function test_a_new_shop_gets_the_starter_catalog(): void
    {
        $this->getJson('/api/v1/catalog/categories')
            ->assertOk()
            ->assertJsonCount(count(DefaultCatalog::categories()), 'data')
            ->assertJsonFragment(['name' => 'شاشات', 'type' => 'part', 'type_label' => 'قطع غيار']);

        $apple = collect($this->getJson('/api/v1/catalog/brands')->assertOk()->json('data'))->firstWhere('name', 'Apple');
        $this->assertContains('iPhone 13', array_column($apple['models'], 'name'));
    }

    public function test_the_starter_catalog_is_seeded_only_once(): void
    {
        DefaultCatalog::seed($this->owner->tenant_id);

        $this->assertSame(count(DefaultCatalog::brands()), $this->inShop($this->owner, fn () => Brand::query()->count()));
    }

    public function test_owner_creates_a_product_with_variants_and_compatible_models(): void
    {
        $product = $this->createProduct();

        $this->assertSame('سكرينة 9D', $product['name']);
        $this->assertSame(['شفاف', 'مطفي'], array_column($product['variants'], 'name'));
        $this->assertSame(15000, $product['variants'][0]['price_retail']);
        $this->assertNull($product['variants'][1]['price_wholesale']);
        $this->assertEqualsCanonicalizing(['Apple iPhone 13', 'Apple iPhone 14'], array_column($product['device_models'], 'full_name'));
        $this->assertSame(1, AuditEntry::query()->where('action', 'products.created')->count());
    }

    public function test_updating_syncs_variants_and_logs_price_changes(): void
    {
        $product = $this->createProduct();
        [$clear, $matte] = $product['variants'];

        $updated = $this->patchJson("/api/v1/products/{$product['id']}", [
            'variants' => [
                ['id' => $clear['id'], 'name' => 'شفاف', 'barcode' => $clear['barcode'], 'price_retail' => 16000, 'price_wholesale' => 9000],
                ['name' => 'خصوصية', 'price_retail' => 25000],
            ],
            'device_model_ids' => [$this->modelId('iPhone 15')],
        ])->assertOk()->json('data');

        $this->assertSame(['شفاف', 'خصوصية'], array_column($updated['variants'], 'name'));
        $this->assertSame($clear['id'], $updated['variants'][0]['id'], 'kept variants keep their id');
        $this->assertNotContains($matte['id'], array_column($updated['variants'], 'id'));
        $this->assertSame(['Apple iPhone 15'], array_column($updated['device_models'], 'full_name'));

        $audit = AuditEntry::query()->where('action', 'products.updated')->sole();
        $this->assertSame([['variant' => 'شفاف', 'field' => 'price_retail', 'from' => 15000, 'to' => 16000]], $audit->properties['price_changes']);
    }

    public function test_updating_only_the_name_leaves_variants_and_models_alone(): void
    {
        $product = $this->createProduct();

        $updated = $this->patchJson("/api/v1/products/{$product['id']}", ['name' => 'سكرينة 9D بلس'])->assertOk()->json('data');

        $this->assertSame('سكرينة 9D بلس', $updated['name']);
        $this->assertCount(2, $updated['variants']);
        $this->assertCount(2, $updated['device_models']);
    }

    public function test_search_by_name_model_barcode_and_arabic_spelling_variants(): void
    {
        $this->createProduct();
        $this->createProduct(['name' => 'جراب سيليكون', 'category_id' => $this->categoryId('جرابات'), 'device_model_ids' => [$this->modelId('Galaxy A54')], 'variants' => [['price_retail' => 8000]]]);
        $this->createProduct(['name' => 'شاحن أنكر 20 وات', 'category_id' => $this->categoryId('شواحن'), 'device_model_ids' => [], 'variants' => [['price_retail' => 45000, 'barcode' => 'ANK-20W']]]);

        $this->assertSame(['سكرينة 9D'], $this->search('q=iphone+13'), 'compatible model');
        $this->assertSame(['سكرينة 9D'], $this->search('q='.urlencode('سكرينه 14')), 'ة and ه');
        $this->assertSame(['شاحن أنكر 20 وات'], $this->search('q='.urlencode('انكر')), 'أ and ا');
        $this->assertSame(['جراب سيليكون'], $this->search('q='.urlencode('جراب a54')), 'every word must match');
        $this->assertSame([], $this->search('q='.urlencode('جراب iphone')));
        $this->assertSame(['شاحن أنكر 20 وات'], $this->search('q=ank-20'), 'barcode');
        $this->assertSame(['جراب سيليكون'], $this->search('device_model_id='.$this->modelId('Galaxy A54')));
        $this->assertSame(['شاحن أنكر 20 وات'], $this->search('category_id='.$this->categoryId('شواحن')));
    }

    public function test_search_treats_like_wildcards_as_text(): void
    {
        $this->createProduct();

        $this->assertSame([], $this->search('q=%25'));
        $this->assertSame([], $this->search('q=_'));
    }

    public function test_barcode_lookup_finds_the_exact_variant(): void
    {
        $product = $this->createProduct();

        $this->getJson('/api/v1/products/barcode/6221234567890')
            ->assertOk()
            ->assertJsonPath('data.product.id', $product['id'])
            ->assertJsonPath('data.variant.name', 'شفاف');

        $this->getJson('/api/v1/products/barcode/622123456')->assertNotFound()->assertJsonPath('code', 'barcode_not_found');
    }

    public function test_barcodes_and_skus_are_unique_per_shop_but_shops_do_not_collide(): void
    {
        $this->createProduct(['sku' => 'SCR-9D']);

        $this->postJson('/api/v1/products', $this->payload(['name' => 'تاني', 'sku' => 'SCR-9D']))
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->postJson('/api/v1/products', $this->payload(['name' => 'تاني', 'sku' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('variants');
        $this->postJson('/api/v1/products', $this->payload(['variants' => [['barcode' => '111', 'price_retail' => 1], ['barcode' => '111', 'price_retail' => 1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('variants.1.barcode');

        $other = $this->newShop();
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/products', [
            ...$this->payload(['sku' => 'SCR-9D']),
            'category_id' => $this->categoryId(of: $other),
            'device_model_ids' => [$this->modelId('iPhone 13', $other)],
        ])->assertCreated();
    }

    public function test_validation(): void
    {
        $this->postJson('/api/v1/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'category_id', 'variants']);

        $this->postJson('/api/v1/products', $this->payload(['variants' => [['price_retail' => -1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('variants.0.price_retail');

        $this->postJson('/api/v1/products', $this->payload(['variants' => [['price_retail' => 100, 'quality_grade' => 'fake']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('variants.0.quality_grade');
    }

    public function test_a_shop_cannot_see_or_use_another_shops_catalog(): void
    {
        $mine = $this->createProduct();

        $other = $this->newShop();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/products/{$mine['id']}")->assertNotFound();
        $this->patchJson("/api/v1/products/{$mine['id']}", ['name' => 'x'])->assertNotFound();
        $this->getJson('/api/v1/products/barcode/6221234567890')->assertNotFound();

        // The first shop's category and models don't exist for the second one.
        $this->postJson('/api/v1/products', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'device_model_ids.0']);

        // Nor can it hijack a variant of another shop's product.
        Sanctum::actingAs($this->owner);
        $foreign = $this->inShop($other, fn () => $this->createProductAs($other));
        $this->patchJson("/api/v1/products/{$mine['id']}", ['variants' => [['id' => $foreign, 'price_retail' => 1]]])
            ->assertNotFound()->assertJsonPath('code', 'variant_not_found');
    }

    /** Creates a product in $user's shop and returns its first variant id. */
    private function createProductAs(User $user): string
    {
        Sanctum::actingAs($user);
        $variantId = $this->postJson('/api/v1/products', [
            'name' => 'غريب',
            'category_id' => $this->categoryId(of: $user),
            'variants' => [['price_retail' => 100]],
        ])->assertCreated()->json('data.variants.0.id');
        Sanctum::actingAs($this->owner);

        return $variantId;
    }

    public function test_cashier_can_view_but_not_manage_products(): void
    {
        $product = $this->createProduct();
        Sanctum::actingAs($this->staffWithRole('cashier'));

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/catalog/brands')->assertOk();
        $this->postJson('/api/v1/products', $this->payload())->assertForbidden();
        $this->patchJson("/api/v1/products/{$product['id']}", ['name' => 'x'])->assertForbidden();
        $this->postJson('/api/v1/catalog/categories', ['name' => 'جديد', 'type' => 'accessory'])->assertForbidden();
        $this->deleteJson('/api/v1/catalog/categories/'.$this->categoryId('متنوع'))->assertForbidden();
    }

    public function test_storekeeper_can_manage_products(): void
    {
        Sanctum::actingAs($this->staffWithRole('storekeeper'));

        $this->postJson('/api/v1/products', $this->payload())->assertCreated();
    }

    public function test_managing_categories_brands_and_models(): void
    {
        $categoryId = $this->postJson('/api/v1/catalog/categories', ['name' => 'استاندات', 'type' => 'accessory'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/catalog/categories', ['name' => 'استاندات', 'type' => 'accessory'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/catalog/categories/{$categoryId}", ['name' => 'استاندات موبايل'])->assertOk()->assertJsonPath('data.name', 'استاندات موبايل');

        $brandId = $this->postJson('/api/v1/catalog/brands', ['name' => 'Google'])->assertCreated()->json('data.id');
        $modelId = $this->postJson("/api/v1/catalog/brands/{$brandId}/models", ['name' => 'Pixel 8'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/catalog/brands/{$brandId}/models", ['name' => 'Pixel 8'])->assertUnprocessable();
        // Same model name under another brand is fine.
        $this->postJson('/api/v1/catalog/brands/'.$this->inShop($this->owner, fn () => Brand::query()->where('name', 'Apple')->value('id')).'/models', ['name' => 'Pixel 8'])->assertCreated();

        $this->assertSame(['Google Pixel 8'], array_column($this->getJson('/api/v1/catalog/device-models?q=pixel+8&brand_id='.$brandId)->json('data'), 'full_name'));

        // Renaming the brand keeps model search in sync.
        $this->patchJson("/api/v1/catalog/brands/{$brandId}", ['name' => 'Google Pixel'])->assertOk();
        $this->assertSame([$modelId], array_column($this->getJson('/api/v1/catalog/device-models?q='.urlencode('google pixel 8'))->json('data'), 'id'));

        $this->deleteJson("/api/v1/catalog/device-models/{$modelId}")->assertNoContent();
        $this->deleteJson("/api/v1/catalog/categories/{$categoryId}")->assertNoContent();
        $this->deleteJson("/api/v1/catalog/brands/{$brandId}")->assertNoContent();
    }

    public function test_entries_used_by_products_cannot_be_deleted(): void
    {
        $this->createProduct(['brand_id' => $this->inShop($this->owner, fn () => Brand::query()->where('name', 'Samsung')->value('id'))]);

        $this->deleteJson('/api/v1/catalog/categories/'.$this->categoryId())->assertUnprocessable()->assertJsonPath('code', 'catalog_entry_in_use');
        $this->deleteJson('/api/v1/catalog/device-models/'.$this->modelId('iPhone 13'))->assertUnprocessable()->assertJsonPath('products', 1);
        // Apple: none of its products, but its models are used for compatibility.
        $this->deleteJson('/api/v1/catalog/brands/'.$this->inShop($this->owner, fn () => Brand::query()->where('name', 'Apple')->value('id')))->assertUnprocessable();
        // Samsung: set as a product's brand.
        $this->deleteJson('/api/v1/catalog/brands/'.$this->inShop($this->owner, fn () => Brand::query()->where('name', 'Samsung')->value('id')))->assertUnprocessable();
    }

    public function test_generating_in_store_barcodes_for_labels(): void
    {
        $product = $this->createProduct(['variants' => [['name' => 'بباركود', 'barcode' => '999', 'price_retail' => 1], ['name' => 'من غير', 'price_retail' => 1]]]);
        [$with, $without] = array_column($product['variants'], 'id');

        $codes = $this->postJson('/api/v1/products/barcodes', ['variant_ids' => [$with, $without]])->assertOk()->json('data');

        $this->assertSame([$without], array_keys($codes), 'variants that have one keep it');
        $this->assertSame('2000000000015', $codes[$without], 'EAN-13, in-store prefix 2, with its check digit');
        $this->assertSame('2000000000022', GenerateBarcodesAction::ean13('200000000002'));
        $this->getJson('/api/v1/products/barcode/2000000000015')->assertOk()->assertJsonPath('data.variant.name', 'من غير');

        // The label screen looks variants up by id (from a product page) or by search.
        $this->getJson('/api/v1/products/labels?'.http_build_query(['ids' => [$without]]))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.barcode', '2000000000015');
        $this->getJson('/api/v1/products/labels?q=999')->assertOk()->assertJsonPath('data.0.id', $with);

        Sanctum::actingAs($this->staffWithRole('cashier'));
        $this->postJson('/api/v1/products/barcodes', ['variant_ids' => [$without]])->assertForbidden();
    }
}
