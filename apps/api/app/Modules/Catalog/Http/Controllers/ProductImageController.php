<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Support\ProductImages;
use App\Support\Audit\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Product photos (the online store, and anywhere a picture helps). Uploads are shrunk by the web
 * first; the API keeps three WebP widths. Media is public: product photos aren't secret.
 */
final class ProductImageController
{
    public function __construct(private readonly ProductImages $images) {}

    public function store(Request $request, Product $product, Auditor $audit): JsonResponse
    {
        abort_unless($request->user()->can('products.manage'), 403);
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], [], ['image' => 'الصورة']);
        $image = $this->images->add($product, $request->file('image'));
        $audit->record('products.image_added', "أضاف صورة للصنف «{$product->name}»", $product);

        return response()->json(['data' => $image->toPublic()], 201);
    }

    public function destroy(Request $request, Product $product, ProductImage $image, Auditor $audit): Response
    {
        abort_unless($request->user()->can('products.manage'), 403);
        abort_unless($image->product_id === $product->id, 404);
        $this->images->remove($image);
        $audit->record('products.image_removed', "شال صورة من الصنف «{$product->name}»", $product);

        return response()->noContent();
    }

    /** The first one is the cover. */
    public function order(Request $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->can('products.manage'), 403);
        $data = $request->validate(['ids' => ['required', 'array', 'max:'.ProductImages::MAX_PER_PRODUCT], 'ids.*' => ['uuid', 'distinct']]);
        DB::transaction(function () use ($product, $data): void {
            foreach ($data['ids'] as $sort => $id) {
                ProductImage::query()->where('product_id', $product->id)->whereKey($id)->update(['sort' => $sort]);
            }
        });

        return response()->json(['data' => $product->images()->get()->map(fn (ProductImage $i) => $i->toPublic())->all()]);
    }

    /** Public, cached for a year (names never change; a new photo gets a new id). */
    public function media(string $path): BinaryFileResponse
    {
        $file = ProductImages::file($path);
        abort_if($file === null, 404);

        return response()->file($file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
