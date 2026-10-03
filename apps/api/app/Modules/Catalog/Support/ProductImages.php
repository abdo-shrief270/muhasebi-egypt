<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Media\WebpImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Product photos on the local disk (backed up with storage/app): each upload becomes WebP in
 * three widths — 320 (lists), 800 (product page), 1600 (zoom) — never upscaled. They're public
 * (the online store, receipts, labels), served with a year's cache: the names never change.
 */
final class ProductImages
{
    public const SIZES = [320, 800, 1600];

    public const MAX_PER_PRODUCT = 8;

    private const DISK = 'local';

    public function add(Product $product, UploadedFile $file): ProductImage
    {
        if (ProductImage::query()->where('product_id', $product->id)->count() >= self::MAX_PER_PRODUCT) {
            throw new DomainRuleException('الصنف عليه '.self::MAX_PER_PRODUCT.' صور، شيل واحدة الأول.', 'too_many_images');
        }
        $source = WebpImage::read((string) $file->get());
        $width = $source->width();
        $height = $source->height();

        return DB::transaction(function () use ($product, $source, $width, $height): ProductImage {
            $image = ProductImage::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'sort' => (int) ProductImage::query()->where('product_id', $product->id)->max('sort') + 1,
                'width' => $width,
                'height' => $height,
            ]);
            foreach (self::SIZES as $size) {
                Storage::disk(self::DISK)->put(self::path($product->tenant_id, $image->id, $size), $source->webp($size));
            }

            return $image;
        });
    }

    public function remove(ProductImage $image): void
    {
        Storage::disk(self::DISK)->delete(array_map(fn (int $size) => self::path($image->tenant_id, $image->id, $size), self::SIZES));
        $image->delete();
    }

    /** All of a product's photos (when the product goes, or the shop is erased). */
    public function removeAll(string $productId): void
    {
        ProductImage::query()->where('product_id', $productId)->get()->each(fn (ProductImage $i) => $this->remove($i));
    }

    public static function path(string $tenantId, string $imageId, int $size): string
    {
        return "products/{$tenantId}/{$imageId}-{$size}.webp";
    }

    /**
     * @return array<int, string> width => URL path (relative to the API's origin)
     */
    public static function urls(string $tenantId, string $imageId): array
    {
        $urls = [];
        foreach (self::SIZES as $size) {
            $urls[$size] = '/api/v1/public/media/'.self::path($tenantId, $imageId, $size);
        }

        return $urls;
    }

    /** The stored file behind a public media path, or null. */
    public static function file(string $path): ?string
    {
        if (preg_match('#^products/[0-9a-f-]{36}/[0-9a-f-]{36}-(\d+)\.webp$#', $path, $m) !== 1 || ! in_array((int) $m[1], self::SIZES, true)) {
            return null;
        }
        $disk = Storage::disk(self::DISK);

        return $disk->exists($path) ? $disk->path($path) : null;
    }
}
