<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private const HEADER = ['اسم الصنف *', 'التصنيف *', 'الماركة', 'كود الصنف', 'النوع', 'الجودة', 'الباركود', 'سعر القطاعي *', 'سعر الجملة', 'سعر الفني', 'سعر الأونلاين', 'حد النواقص', 'الموديلات'];

    private const STOCK_HEADER = ['اسم الصنف', 'التصنيف', 'الباركود', 'سعر القطاعي', 'الكمية', 'سعر التكلفة'];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->newShop();
        Sanctum::actingAs($this->owner);
    }

    private function newShop(): User
    {
        $owner = $this->registerShop(ShopType::AccessoriesAndRepair);
        app(EventRelay::class)->publishPending(); // starter catalog

        return $owner;
    }

    private function inShop(callable $callback, ?User $user = null): mixed
    {
        return app(CurrentTenant::class)->runAs(($user ?? $this->owner)->tenant_id, $callback);
    }

    /**
     * @param  list<list<string|int|float>>  $rows
     */
    private function xlsx(array $rows, ?array $header = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header ?? self::HEADER));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'أصناف.xlsx', null, null, true);
    }

    private function preview(UploadedFile $file): TestResponse
    {
        return $this->post('/api/v1/products/import/preview', ['file' => $file], ['Accept' => 'application/json']);
    }

    private function import(UploadedFile $file, bool $skipInvalid = false): TestResponse
    {
        return $this->post('/api/v1/products/import', ['file' => $file, 'skip_invalid' => $skipInvalid ? '1' : '0'], ['Accept' => 'application/json']);
    }

    private function productNamed(string $name): Product
    {
        return $this->inShop(fn () => Product::query()->with(['variants', 'deviceModels', 'brand', 'category'])->where('name', $name)->sole());
    }

    public function test_the_downloaded_template_imports_as_is(): void
    {
        $response = $this->get('/api/v1/products/import/template')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $summary = $this->import(new UploadedFile($path, 'نموذج.xlsx', null, null, true))->assertOk()->json('data');

        $this->assertSame(4, $summary['valid_rows']);
        $this->assertSame(3, $summary['new_products']);
        $this->assertSame(4, $summary['new_variants']);
        $this->assertSame([], $summary['errors']);
        $this->assertSame([], $summary['warnings'], 'the example models exist in the starter catalog');

        $screen = $this->productNamed('سكرينة 9D');
        $this->assertSame(['شفاف', 'مطفي'], $screen->variants->pluck('name')->all());
        $this->assertSame([15000, 17500], $screen->variants->pluck('price_retail')->all());
        $this->assertEqualsCanonicalizing(['iPhone 13', 'iPhone 14'], $screen->deviceModels->pluck('name')->all());
        $this->assertSame('service_pack', $this->productNamed('شاشة iPhone 11')->variants->sole()->quality_grade->value);
    }

    public function test_preview_reports_without_writing(): void
    {
        $summary = $this->preview($this->xlsx([
            ['جراب سيليكون', 'جرابات', 'Spigen', '', 'أسود', '', '111', '120', '', '', '', '', 'Galaxy A54'],
            ['جراب سيليكون', 'جرابات', 'Spigen', '', 'أزرق', '', '112', '120', '', '', '', '', 'Galaxy A54'],
            ['كابل', 'كابلات خاصة', '', '', '', '', '', '60', '', '', '', '', ''],
        ]))->assertOk()->json('data');

        $this->assertSame(3, $summary['valid_rows']);
        $this->assertSame(2, $summary['new_products']);
        $this->assertSame(3, $summary['new_variants']);
        $this->assertSame(['كابلات خاصة'], $summary['new_categories']);
        $this->assertSame(['Spigen'], $summary['new_brands']);
        $this->assertSame(0, $this->inShop(fn () => Product::query()->count()));
        $this->assertFalse($this->inShop(fn () => Brand::query()->where('name', 'Spigen')->exists()));
    }

    public function test_import_creates_missing_categories_and_brands(): void
    {
        $this->import($this->xlsx([
            ['جراب سيليكون', 'جرابات', 'Spigen', 'SP-1', 'أسود', '', '111', '120', '80', '', '', '3', 'Samsung Galaxy A54'],
            ['كابل', 'كابلات خاصة', '', '', '', '', '', '60', '', '', '', '', ''],
        ]))->assertOk();

        $case = $this->productNamed('جراب سيليكون');
        $this->assertSame('Spigen', $case->brand->name);
        $this->assertSame('SP-1', $case->sku);
        $this->assertSame(['Galaxy A54'], $case->deviceModels->pluck('name')->all());
        $this->assertSame(8000, $case->variants->sole()->price_wholesale);
        $this->assertSame(3, $case->variants->sole()->min_stock);
        $this->assertSame('other', $this->productNamed('كابل')->category->type->value);
        $this->assertSame(1, AuditEntry::query()->where('action', 'products.imported')->count());
    }

    public function test_numbers_as_shops_type_them(): void
    {
        $this->import($this->xlsx([
            ['شاشة A', 'شاشات', '', '', '', 'اورجينال', '', '١٢٥٠', '1,100.50', '1200 ج', '', '٢', ''],
        ]))->assertOk();

        $variant = $this->productNamed('شاشة A')->variants->sole();
        $this->assertSame(125000, $variant->price_retail);
        $this->assertSame(110050, $variant->price_wholesale);
        $this->assertSame(120000, $variant->price_technician);
        $this->assertSame(2, $variant->min_stock);
        $this->assertSame('original', $variant->quality_grade->value);
    }

    public function test_invalid_rows_block_the_import_unless_skipped(): void
    {
        $file = fn () => $this->xlsx([
            ['سليم', 'جرابات', '', '', '', '', '500', '100', '', '', '', '', ''],
            ['', 'جرابات', '', '', '', '', '', '100', '', '', '', '', ''],
            ['سعره غلط', 'جرابات', '', '', '', '', '', 'مية', '', '', '', '', ''],
            ['باركود مكرر', 'جرابات', '', '', '', '', '500', '100', '', '', '', '', ''],
            ['موديل مش موجود', 'جرابات', '', '', '', 'ممتاز', '', '100', '', '', '', '', 'Nokia 3310'],
        ]);

        $summary = $this->preview($file())->assertOk()->json('data');
        $this->assertSame(2, $summary['valid_rows']);
        $this->assertSame([3, 4, 5], array_column($summary['errors'], 'row'));
        $this->assertStringContainsString('اسم الصنف فاضي', $summary['errors'][0]['messages'][0]);
        $this->assertStringContainsString('سعر القطاعي مش رقم', $summary['errors'][1]['messages'][0]);
        $this->assertStringContainsString('متكرر (أول مرة في صف 2)', $summary['errors'][2]['messages'][0]);
        $this->assertSame([6], array_column($summary['warnings'], 'row'), 'unknown quality and model are warnings, not errors');

        $this->import($file())->assertUnprocessable()->assertJsonPath('code', 'import_has_errors');
        $this->assertSame(0, $this->inShop(fn () => Product::query()->count()));

        $this->import($file(), skipInvalid: true)->assertOk()->assertJsonPath('data.valid_rows', 2);
        $this->assertEqualsCanonicalizing(['سليم', 'موديل مش موجود'], $this->inShop(fn () => Product::query()->pluck('name')->all()));
    }

    public function test_an_existing_barcode_updates_that_variants_prices(): void
    {
        $this->import($this->xlsx([['سكرينة', 'سكرينات حماية', '', '', '', '', '777', '100', '60', '', '', '5', '']]))->assertOk();

        $summary = $this->import($this->xlsx([
            // Different name, empty wholesale: the barcode decides, and empty cells keep today's value.
            ['اسم تاني', 'جرابات', '', '', '', '', '777', '130', '', '', '', '', 'iPhone 15'],
        ]))->assertOk()->json('data');

        $this->assertSame(1, $summary['updated_variants']);
        $this->assertSame(0, $summary['new_products']);
        $variant = $this->productNamed('سكرينة')->variants->sole();
        $this->assertSame(13000, $variant->price_retail);
        $this->assertSame(6000, $variant->price_wholesale);
        $this->assertSame(5, $variant->min_stock);
        $this->assertSame(['iPhone 15'], $this->productNamed('سكرينة')->deviceModels->pluck('name')->all());
    }

    public function test_same_name_and_category_joins_the_existing_product(): void
    {
        $this->import($this->xlsx([['جراب', 'جرابات', '', '', 'أسود', '', '', '100', '', '', '', '', '']]))->assertOk();

        $summary = $this->import($this->xlsx([
            ['جراب', 'جرابات', '', '', 'أسود', '', '', '110', '', '', '', '', ''],   // same variant: updated
            ['جراب', 'جرابات', '', '', 'أحمر', '', '', '115', '', '', '', '', ''],  // new variant
        ]))->assertOk()->json('data');

        $this->assertSame([1, 1, 0], [$summary['updated_variants'], $summary['new_variants'], $summary['new_products']]);
        $this->assertSame([11000, 11500], $this->productNamed('جراب')->variants->pluck('price_retail')->all());
    }

    public function test_sku_already_used_is_an_error(): void
    {
        $this->import($this->xlsx([['أول', 'جرابات', '', 'X-1', '', '', '', '100', '', '', '', '', '']]))->assertOk();

        $this->preview($this->xlsx([['تاني', 'جرابات', '', 'x-1', '', '', '', '100', '', '', '', '', '']]))
            ->assertOk()
            ->assertJsonPath('data.errors.0.messages.0', 'كود الصنف x-1 مستخدم لصنف تاني');
    }

    public function test_csv_works_too(): void
    {
        $csv = "\u{FEFF}".implode(',', ['اسم الصنف', 'التصنيف', 'سعر القطاعي'])."\n"."سماعة,سماعات,250\n";

        $this->import(UploadedFile::fake()->createWithContent('أصناف.csv', $csv))->assertOk();

        $this->assertSame(25000, $this->productNamed('سماعة')->variants->sole()->price_retail);
    }

    public function test_bad_files(): void
    {
        $this->preview($this->xlsx([['x', 'y']], header: ['الاسم', 'حاجة']))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_missing_columns')
            ->assertJsonPath('missing', ['التصنيف', 'سعر القطاعي']);

        $this->preview($this->xlsx([]))->assertUnprocessable()->assertJsonPath('code', 'import_empty');
        $this->preview(UploadedFile::fake()->create('صورة.png', 10, 'image/png'))->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_another_shops_barcode_does_not_match(): void
    {
        $this->import($this->xlsx([['صنفي', 'جرابات', '', '', '', '', '999', '100', '', '', '', '', '']]))->assertOk();

        $other = $this->newShop();
        Sanctum::actingAs($other);
        $this->import($this->xlsx([['صنفه', 'جرابات', '', '', '', '', '999', '300', '', '', '', '', '']]))
            ->assertOk()->assertJsonPath('data.new_products', 1);

        $this->assertSame(10000, $this->productNamed('صنفي')->variants->sole()->price_retail);
    }

    public function test_only_managers_can_import(): void
    {
        $cashier = User::create([
            'tenant_id' => $this->owner->tenant_id,
            'name' => 'كاشير',
            'phone' => '+201155556666',
            'password' => 'password',
            'role_id' => $this->inShop(fn () => Role::query()->where('key', 'cashier')->value('id')),
        ]);
        Sanctum::actingAs($cashier);

        $this->get('/api/v1/products/import/template', ['Accept' => 'application/json'])->assertForbidden();
        $this->preview($this->xlsx([['x', 'جرابات', '', '', '', '', '', '1', '', '', '', '', '']]))->assertForbidden();
    }

    public function test_categories_matched_despite_spelling(): void
    {
        $this->preview($this->xlsx([['x', 'سكرينات حمايه', '', '', '', '', '', '1', '', '', '', '', '']]))
            ->assertOk()->assertJsonPath('data.new_categories', []);
        $this->assertTrue($this->inShop(fn () => Category::query()->where('name', 'سكرينات حماية')->exists()));
        $this->assertSame(0, $this->inShop(fn () => ProductVariant::query()->count()));
    }

    private function stockOf(string $productName): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity(
            Branch::query()->value('id'),
            $this->productNamed($productName)->variants->sole()->id,
        ));
    }

    public function test_quantity_and_cost_become_opening_stock(): void
    {
        $summary = $this->import($this->xlsx([
            ['جراب', 'جرابات', 'B-1', '100', '12', '40'],
            ['كابل', 'كابلات', '', '60', '', ''],
        ], header: self::STOCK_HEADER))->assertOk()->json('data');

        $this->assertSame(12, $summary['stock_units']);
        $this->assertSame(12, $this->stockOf('جراب'));
        $this->assertSame(0, $this->stockOf('كابل'));

        $variantId = $this->productNamed('جراب')->variants->sole()->id;
        $this->getJson("/api/v1/inventory/variants/{$variantId}/movements")
            ->assertJsonPath('data.item.avg_cost', 4000)
            ->assertJsonPath('data.movements.0.type', 'opening');
    }

    public function test_quantity_is_not_recorded_twice(): void
    {
        $this->import($this->xlsx([['جراب', 'جرابات', 'B-1', '100', '12', '40']], header: self::STOCK_HEADER))->assertOk();

        $summary = $this->import($this->xlsx([['جراب', 'جرابات', 'B-1', '110', '50', '40']], header: self::STOCK_HEADER))->assertOk()->json('data');

        $this->assertSame(0, $summary['stock_units']);
        $this->assertStringContainsString('ليه رصيد في الفرع بالفعل', $summary['warnings'][0]['messages'][0]);
        $this->assertSame(12, $this->stockOf('جراب'));
        $this->assertSame(11000, $this->productNamed('جراب')->variants->sole()->price_retail, 'prices still update');
    }

    public function test_quantity_needs_the_stock_permission(): void
    {
        $roleId = $this->inShop(fn () => Role::create(['key' => 'catalog_only', 'name' => 'أصناف بس', 'permissions' => ['products.view', 'products.manage']])->id);
        $userId = $this->postJson('/api/v1/users', [
            'name' => 'مدخل بيانات', 'phone' => '01133334444', 'password' => 'password',
            'role_id' => $roleId, 'branch_ids' => [$this->inShop(fn () => Branch::query()->value('id'))],
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs(User::query()->findOrFail($userId));

        $summary = $this->import($this->xlsx([['جراب', 'جرابات', 'B-1', '100', '12', '40']], header: self::STOCK_HEADER))->assertOk()->json('data');

        $this->assertSame(0, $summary['stock_units']);
        $this->assertStringContainsString('مش معاك صلاحية', $summary['warnings'][0]['messages'][0]);
        $this->assertSame(0, $this->stockOf('جراب'));
    }
}
