<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Actions;

use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Support\PhotoStore;
use App\Modules\UsedDevices\Support\PublicPhotos;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The device's own photos after buying it (the card photos never change): the ones «سوق محاسبي»
 * shows. At most MAX; only while the device is in stock.
 */
final class DevicePhotosAction
{
    public const MAX = 4;

    public function __construct(
        private readonly PhotoStore $store,
        private readonly PublicPhotos $public,
        private readonly Auditor $audit,
    ) {}

    /** @param  list<UploadedFile>  $files */
    public function add(UsedDevice $device, array $files): void
    {
        $stored = [];
        try {
            DB::transaction(function () use ($device, $files, &$stored): void {
                $device = UsedDevice::query()->lockForUpdate()->findOrFail($device->id);
                $this->ensureInStock($device);
                $count = $device->photos()->where('kind', 'device')->count();
                if ($count + count($files) > self::MAX) {
                    throw new DomainRuleException('أقصى عدد '.self::MAX.' صور للجهاز.', 'device_photos_limit');
                }
                foreach ($files as $file) {
                    $path = $this->store->put($device->tenant_id, $device->id, $file);
                    $stored[] = $path;
                    $device->photos()->create([
                        'tenant_id' => $device->tenant_id,
                        'kind' => 'device',
                        'path' => $path,
                        'mime' => (string) $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                        'created_at' => now(),
                    ]);
                }
                $device->touch();
                $this->audit->record('used_devices.photos_added', 'ضاف '.count($files)." صورة للجهاز المستعمل {$device->reference()}", $device);
            });
        } catch (Throwable $e) {
            $this->store->delete($stored);

            throw $e;
        }
    }

    public function remove(UsedDevice $device, int $photoId): void
    {
        DB::transaction(function () use ($device, $photoId): void {
            $device = UsedDevice::query()->lockForUpdate()->findOrFail($device->id);
            $this->ensureInStock($device);
            /** @var UsedDevicePhoto $photo */
            $photo = $device->photos()->where('kind', 'device')->whereKey($photoId)->firstOrFail();
            $photo->delete();
            $device->touch();
            $this->audit->record('used_devices.photo_removed', "شال صورة من الجهاز المستعمل {$device->reference()}", $device);
            DB::afterCommit(function () use ($photo): void {
                $this->store->delete([$photo->path]);
                $this->public->forget($photo);
            });
        });
    }

    private function ensureInStock(UsedDevice $device): void
    {
        if ($device->status !== UsedDevice::IN_STOCK) {
            throw new DomainRuleException('الجهاز اتباع؛ صوره مبتتغيرش.', 'device_not_in_stock');
        }
    }
}
