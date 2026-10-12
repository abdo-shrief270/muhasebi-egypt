<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Support\Media\WebpImage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * The device photos (never the card's) as public WebP for «سوق محاسبي», made from the encrypted
 * original the first time they're asked for and served only while the device is for sale.
 */
final class PublicPhotos
{
    private const DISK = 'local';

    public const WIDTH = 1000;

    public static function url(string $deviceId, int $photoId): string
    {
        return "/api/v1/public/media/used/{$deviceId}/{$photoId}.webp";
    }

    private static function path(string $tenantId, string $deviceId, int $photoId): string
    {
        return "market/used/{$tenantId}/{$deviceId}/{$photoId}.webp";
    }

    /** The WebP file's absolute path, made now if needed. */
    public function file(UsedDevicePhoto $photo): ?string
    {
        if ($photo->isIdCard()) {
            return null;
        }
        $disk = Storage::disk(self::DISK);
        $path = self::path($photo->tenant_id, $photo->used_device_id, $photo->id);
        if (! $disk->exists($path)) {
            $raw = $disk->get($photo->path);
            if ($raw === null) {
                return null;
            }
            $disk->put($path, WebpImage::read(Crypt::decryptString($raw))->webp(self::WIDTH, 80));
        }

        return $disk->path($path);
    }

    public function forget(UsedDevicePhoto $photo): void
    {
        Storage::disk(self::DISK)->delete(self::path($photo->tenant_id, $photo->used_device_id, $photo->id));
    }
}
