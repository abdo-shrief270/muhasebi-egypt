<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Http\Requests\SaveProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ProductController
{
    private const WITH = ['category', 'brand', 'variants', 'deviceModels.brand', 'images'];

    public function __construct(private readonly CurrentTenant $tenant) {}

    /**
     * ?q= (name, SKU, barcode, brand or compatible model) &category_id= &brand_id= &device_model_id= &active=0|1
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(self::WITH)
            ->when($request->filled('q'), fn (Builder $q) => $q->search((string) $request->query('q')))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn (Builder $q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('device_model_id'), fn (Builder $q) => $q->whereHas(
                'deviceModels',
                fn (Builder $m) => $m->whereKey($request->integer('device_model_id')),
            ))
            ->when($request->has('active'), fn (Builder $q) => $q->where('is_active', $request->boolean('active')))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(30);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::WITH));
    }

    public function store(SaveProductRequest $request, SaveProductAction $action): JsonResponse
    {
        $product = $action->handle(
            $this->tenant->idOrFail(),
            $request->productData(),
            $request->variants(),
            $request->deviceModelIds() ?? [],
        );

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProductAction $action): ProductResource
    {
        return new ProductResource($action->handle(
            $this->tenant->idOrFail(),
            $request->productData(),
            $request->variants(),
            $request->deviceModelIds(),
            $product,
        ));
    }

    /** Show / hide products on the online store, several at once (the products list). */
    public function online(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid', 'distinct'],
            'visible' => ['required', 'boolean'],
        ]);
        $count = Product::query()->whereKey($data['ids'])->update(['online_visible' => $data['visible']]);
        $audit->record('products.online_visibility', ($data['visible'] ? 'أظهر ' : 'خبّى ')."{$count} صنف ".($data['visible'] ? 'في' : 'من').' المتجر الأونلاين');

        return response()->json(['data' => ['updated' => $count]]);
    }

    /** Exact barcode match, for scanners. */
    public function barcode(string $code): JsonResponse
    {
        $variant = ProductVariant::query()->with('product.category')->where('barcode', $code)->first();

        if ($variant === null) {
            return response()->json(['message' => 'مفيش صنف بالباركود ده.', 'code' => 'barcode_not_found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => [
            'product' => new ProductResource($variant->product),
            'variant' => new ProductVariantResource($variant),
        ]]);
    }
}
