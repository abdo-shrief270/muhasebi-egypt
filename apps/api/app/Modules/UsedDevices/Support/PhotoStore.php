<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Card and device photos on the private local disk, encrypted with the app key (a copied disk
 * or backup shows nothing), read back only through the API.
 */
final class PhotoStore
{
    private const DISK = 'local';

    /** Writes the file; returns its path. Remove it with delete() if the transaction fails. */
    public function put(string $tenantId, string $deviceId, UploadedFile $file): string
    {
        $path = "used-devices/{$tenantId}/{$deviceId}/".Str::uuid7()->toString().'.bin';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString((string) $file->get()));

        return $path;
    }

    public function response(UsedDevicePhoto $photo): Response
    {
        $raw = Storage::disk(self::DISK)->get($photo->path);
        abort_if($raw === null, 404);

        return response(Crypt::decryptString($raw), 200, [
            'Content-Type' => $photo->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * @param  list<string>  $paths
     */
    public function delete(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
