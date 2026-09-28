<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\DeleteCatalogEntryAction;
use App\Modules\Catalog\Http\Requests\SaveCatalogEntryRequest;
use App\Modules\Catalog\Http\Resources\BrandResource;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Http\Resources\DeviceModelResource;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Support\SearchText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Categories, brands and phone models: the lists products are organised by.
 */
final class CatalogListsController
{
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->withCount('products')->orderBy('sort')->orderBy('name')->get());
    }

    public function storeCategory(SaveCatalogEntryRequest $request): JsonResponse
    {
        $category = Category::create([...$request->validated(), 'sort' => (int) Category::query()->max('sort') + 1]);

        return (new CategoryResource($category))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateCategory(SaveCatalogEntryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource(tap($category)->update($request->validated()));
    }

    public function destroyCategory(Category $category, DeleteCatalogEntryAction $action): HttpResponse
    {
        $action->handle($category);

        return response()->noContent();
    }

    /** Brands with their phone models. */
    public function brands(): AnonymousResourceCollection
    {
        return BrandResource::collection(
            Brand::query()->with(['deviceModels' => fn ($q) => $q->orderBy('name')])->orderBy('sort')->orderBy('name')->get(),
        );
    }

    public function storeBrand(SaveCatalogEntryRequest $request): JsonResponse
    {
        $brand = Brand::create([...$request->validated(), 'sort' => (int) Brand::query()->max('sort') + 1]);

        return (new BrandResource($brand->load('deviceModels')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateBrand(SaveCatalogEntryRequest $request, Brand $brand): BrandResource
    {
        $brand->update($request->validated());
        // The models' search text includes the brand name.
        $brand->deviceModels->each->save();

        return new BrandResource($brand->load('deviceModels'));
    }

    public function destroyBrand(Brand $brand, DeleteCatalogEntryAction $action): HttpResponse
    {
        $action->handle($brand);

        return response()->noContent();
    }

    /** ?q= "iphone 13" / "a54" &brand_id= — for pickers; at most 30. */
    public function models(Request $request): AnonymousResourceCollection
    {
        $models = DeviceModel::query()
            ->with('brand')
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                foreach (SearchText::tokens((string) $request->query('q')) as $token) {
                    $q->where('search_name', 'like', SearchText::like($token));
                }
            })
            ->orderBy('search_name')
            ->limit(30)
            ->get();

        return DeviceModelResource::collection($models);
    }

    public function storeModel(SaveCatalogEntryRequest $request, Brand $brand): JsonResponse
    {
        $model = $brand->deviceModels()->create([...$request->validated(), 'tenant_id' => $brand->tenant_id]);

        return (new DeviceModelResource($model->load('brand')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateModel(SaveCatalogEntryRequest $request, DeviceModel $deviceModel): DeviceModelResource
    {
        return new DeviceModelResource(tap($deviceModel)->update($request->validated())->load('brand'));
    }

    public function destroyModel(DeviceModel $deviceModel, DeleteCatalogEntryAction $action): HttpResponse
    {
        $action->handle($deviceModel);

        return response()->noContent();
    }
}
