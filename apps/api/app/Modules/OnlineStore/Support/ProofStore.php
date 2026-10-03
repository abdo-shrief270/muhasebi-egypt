<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Support\Media\WebpImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The customer's transfer screenshot: re-encoded to WebP (nothing else of the upload is kept) on
 * the private local disk, shown only to the shop (GET /online-store/orders/{id}/proof).
 */
final class ProofStore
{
    private const DISK = 'local';

    /** @return string the file's name for `online_orders.proof` */
    public function put(string $tenantId, string $orderId, UploadedFile $file): string
    {
        $name = Str::lower(Str::random(16));
        Storage::disk(self::DISK)->put(self::path($tenantId, $orderId, $name), WebpImage::read((string) $file->get())->webp(1600, 80));

        return $name;
    }

    public function file(OnlineOrder $order): ?string
    {
        if ($order->proof === null) {
            return null;
        }
        $path = self::path($order->tenant_id, $order->id, $order->proof);

        return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->path($path) : null;
    }

    private static function path(string $tenantId, string $orderId, string $name): string
    {
        return "online-orders/{$tenantId}/{$orderId}-{$name}.webp";
    }
}
