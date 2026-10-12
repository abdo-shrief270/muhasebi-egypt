<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Http\Controllers;

use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Support\PublicPhotos;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** A used device's own photo for «سوق محاسبي» (no login), while the device is for sale. Never a card photo. */
final class PublicPhotoController
{
    public function __invoke(string $device, int $photo, PublicPhotos $photos): BinaryFileResponse
    {
        $model = UsedDevice::query()->whereKey($device)->where('status', UsedDevice::IN_STOCK)->first();
        abort_if($model === null, 404);
        $file = UsedDevicePhoto::query()->where('used_device_id', $model->id)->where('kind', 'device')->whereKey($photo)->first();
        $path = $file instanceof UsedDevicePhoto ? $photos->file($file) : null;
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            // Short: a sold device's photos stop being served.
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
