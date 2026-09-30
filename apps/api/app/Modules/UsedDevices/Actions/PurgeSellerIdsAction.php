<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Actions;

use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Modules\UsedDevices\Models\UsedDeviceSetting;
use App\Modules\UsedDevices\Support\PhotoStore;
use App\Support\Audit\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * For the current shop: erased sellers whose last sale to the shop is older than the retention
 * period lose what was kept for the anti-theft record — the national ID (and what it says: birth
 * date, gender, governorate) and the photos of their card. The devices and their money stay.
 */
final class PurgeSellerIdsAction
{
    public function __construct(
        private readonly PhotoStore $photos,
        private readonly Auditor $audit,
    ) {}

    /** @return int how many sellers were purged */
    public function handle(): int
    {
        $cutoff = now()->subYears(UsedDeviceSetting::retentionYears());
        $count = 0;

        UsedDeviceSeller::query()
            ->whereNotNull('erased_at')
            ->whereNull('id_purged_at')
            ->whereDoesntHave('devices', fn ($q) => $q->where('bought_at', '>', $cutoff))
            ->orderBy('id')
            ->each(function (UsedDeviceSeller $seller) use (&$count): void {
                $paths = [];
                DB::transaction(function () use ($seller, &$paths): void {
                    $deviceIds = UsedDevice::query()->where('seller_id', $seller->id)->pluck('id');
                    $idPhotos = UsedDevicePhoto::query()->whereIn('used_device_id', $deviceIds)->whereIn('kind', UsedDevicePhoto::ID_KINDS);
                    $paths = $idPhotos->pluck('path')->all();
                    $idPhotos->delete();

                    $seller->forceFill([
                        'national_id' => null,
                        'national_id_hash' => null,
                        'birth_date' => null,
                        'gender' => null,
                        'governorate' => null,
                        'id_purged_at' => now(),
                    ])->save();

                    $this->audit->record('used_devices.seller_id_purged', 'اتمسح الرقم القومي وصور بطاقة بايع جهاز مستعمل (خلصت مدة الاحتفاظ)', $seller, ['photos' => count($paths)]);
                });
                // Files go once the rows are gone for good.
                $this->photos->delete($paths);
                $count++;
            });

        return $count;
    }
}
