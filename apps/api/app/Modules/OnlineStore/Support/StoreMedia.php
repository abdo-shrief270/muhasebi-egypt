<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Models\OnlineStore;
use App\Support\Media\WebpImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The store's logo and cover: WebP on the local disk under a new name per upload (so the year-long
 * cache never shows an old one), served publicly by GET /public/media/stores/{tenant}/{file}.
 */
final class StoreMedia
{
    public const SIZES = ['logo' => [128, 512], 'cover' => [800, 1600]];

    private const DISK = 'local';

    public function put(OnlineStore $store, string $kind, UploadedFile $file): void
    {
        $image = WebpImage::read((string) $file->get());
        $name = Str::lower(Str::random(16));
        foreach (self::SIZES[$kind] as $width) {
            Storage::disk(self::DISK)->put(self::path($store->tenant_id, "{$kind}-{$name}", $width), $image->webp($width));
        }
        $this->remove($store, $kind);
        $store->forceFill([$kind => $name])->save();
    }

    public function remove(OnlineStore $store, string $kind): void
    {
        if ($store->{$kind} !== null) {
            Storage::disk(self::DISK)->delete(array_map(fn (int $w) => self::path($store->tenant_id, "{$kind}-{$store->{$kind}}", $w), self::SIZES[$kind]));
            $store->forceFill([$kind => null])->save();
        }
    }

    public static function path(string $tenantId, string $name, int $width): string
    {
        return "stores/{$tenantId}/{$name}-{$width}.webp";
    }

    /**
     * @return array<int, string>|null width => URL path (relative to the API's origin)
     */
    public static function urls(string $tenantId, string $kind, ?string $name): ?array
    {
        if ($name === null) {
            return null;
        }
        $urls = [];
        foreach (self::SIZES[$kind] as $width) {
            $urls[$width] = '/api/v1/public/media/'.self::path($tenantId, "{$kind}-{$name}", $width);
        }

        return $urls;
    }

    public static function file(string $path): ?string
    {
        if (preg_match('#^stores/[0-9a-f-]{36}/(logo|cover)-[a-z0-9]{16}-(\d+)\.webp$#', $path, $m) !== 1 || ! in_array((int) $m[2], self::SIZES[$m[1]], true)) {
            return null;
        }
        $disk = Storage::disk(self::DISK);

        return $disk->exists($path) ? $disk->path($path) : null;
    }
}
