<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\QualityGrade;
use App\Modules\Catalog\Models\Product;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveProductRequest extends FormRequest
{
    /** 1,000,000,000 EGP in piasters: far above any real price, well inside bigint. */
    private const MAX_PRICE = 100_000_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('products.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->idOrFail();
        $creating = $this->product() === null;
        $required = $creating ? 'required' : 'sometimes';
        $ofTenant = fn (Builder $q) => $q->where('tenant_id', $tenantId);
        $price = ['integer', 'min:0', 'max:'.self::MAX_PRICE];

        return [
            'name' => [$required, 'string', 'max:190'],
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')->where($ofTenant)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where($ofTenant)],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->where($ofTenant)->ignore($this->product()?->id)],
            'track_serial' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'device_model_ids' => ['sometimes', 'array', 'max:200'],
            'device_model_ids.*' => ['integer', 'distinct', Rule::exists('device_models', 'id')->where($ofTenant)],

            'variants' => [$required, 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'uuid'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.quality_grade' => ['nullable', Rule::enum(QualityGrade::class)],
            'variants.*.barcode' => ['nullable', 'string', 'max:64', 'distinct'],
            'variants.*.price_retail' => ['required', ...$price],
            'variants.*.price_wholesale' => ['nullable', ...$price],
            'variants.*.price_technician' => ['nullable', ...$price],
            'variants.*.price_online' => ['nullable', ...$price],
            'variants.*.min_stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الصنف',
            'category_id' => 'التصنيف',
            'brand_id' => 'الماركة',
            'sku' => 'كود الصنف',
            'variants' => 'المتغيرات',
            'variants.*.name' => 'اسم المتغير',
            'variants.*.barcode' => 'الباركود',
            'variants.*.price_retail' => 'سعر القطاعي',
            'variants.*.price_wholesale' => 'سعر الجملة',
            'variants.*.price_technician' => 'سعر الفني',
            'variants.*.price_online' => 'سعر الأونلاين',
            'variants.*.min_stock' => 'حد النواقص',
            'device_model_ids.*' => 'الموديل',
        ];
    }

    /**
     * A barcode may be reused between variants of this product, never taken from another product.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $barcodes = array_values(array_filter(array_column($this->input('variants', []), 'barcode')));

            if ($barcodes === []) {
                return;
            }

            $taken = DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('product_variants.tenant_id', app(CurrentTenant::class)->idOrFail())
                ->whereIn('product_variants.barcode', $barcodes)
                ->when($this->product(), fn ($q, Product $p) => $q->where('product_variants.product_id', '!=', $p->id))
                ->first(['product_variants.barcode', 'products.name']);

            if ($taken !== null) {
                $validator->errors()->add('variants', "الباركود {$taken->barcode} مستخدم بالفعل للصنف «{$taken->name}».");
            }
        }];
    }

    public function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array{category_id?: int, brand_id?: int|null, name?: string, sku?: string|null, track_serial?: bool, is_active?: bool, notes?: string|null}
     */
    public function productData(): array
    {
        /** @var array{category_id?: int, brand_id?: int|null, name?: string, sku?: string|null, track_serial?: bool, is_active?: bool, notes?: string|null} */
        return $this->safe()->only(['category_id', 'brand_id', 'name', 'sku', 'track_serial', 'is_active', 'notes']);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function variants(): ?array
    {
        if (! $this->has('variants')) {
            return null;
        }

        $fields = ['id', 'name', 'quality_grade', 'barcode', 'price_retail', 'price_wholesale', 'price_technician', 'price_online', 'min_stock', 'is_active'];

        return array_values(array_map(
            fn (array $row): array => array_intersect_key($row, array_flip($fields)),
            $this->validated('variants'),
        ));
    }

    /**
     * @return list<int>|null
     */
    public function deviceModelIds(): ?array
    {
        return $this->has('device_model_ids') ? array_map(intval(...), $this->validated('device_model_ids')) : null;
    }
}
