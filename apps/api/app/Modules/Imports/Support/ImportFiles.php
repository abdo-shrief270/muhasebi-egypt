<?php

declare(strict_types=1);

namespace App\Modules\Imports\Support;

use App\Modules\Imports\Models\ImportAttachment;
use App\Modules\Imports\Models\ImportPayment;
use App\Support\Media\WebpImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The imports' papers on the private disk: payment receipts (re-encoded to WebP) and a shipment's
 * attachments (PDF / photos, kept as they are). Only the shop gets them, through the API.
 */
final class ImportFiles
{
    private const DISK = 'local';

    public const ATTACHMENT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function putProof(string $tenantId, string $paymentId, UploadedFile $file): string
    {
        $name = Str::lower(Str::random(16));
        Storage::disk(self::DISK)->put(self::proofPath($tenantId, $paymentId, $name), WebpImage::read((string) $file->get())->webp(1600, 80));

        return $name;
    }

    public function proof(ImportPayment $payment): ?string
    {
        if ($payment->proof === null) {
            return null;
        }
        $path = self::proofPath($payment->tenant_id, $payment->id, $payment->proof);

        return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->path($path) : null;
    }

    /** @return string the stored path */
    public function putAttachment(string $tenantId, string $shipmentId, UploadedFile $file, string $mime): string
    {
        $path = "imports/{$tenantId}/{$shipmentId}/".Str::uuid7().'.'.self::ATTACHMENT_TYPES[$mime];
        Storage::disk(self::DISK)->put($path, (string) $file->get());

        return $path;
    }

    public function attachment(ImportAttachment $attachment): ?string
    {
        $disk = Storage::disk(self::DISK);

        return $disk->exists($attachment->path) ? $disk->path($attachment->path) : null;
    }

    public function delete(ImportAttachment $attachment): void
    {
        Storage::disk(self::DISK)->delete($attachment->path);
    }

    private static function proofPath(string $tenantId, string $paymentId, string $name): string
    {
        return "imports/{$tenantId}/payments/{$paymentId}-{$name}.webp";
    }
}
