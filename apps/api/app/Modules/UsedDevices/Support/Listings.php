<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

use App\Modules\UsedDevices\Contracts\UsedListings;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Support\Modules\ModuleAccess;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class Listings implements UsedListings
{
    /** The checks a buyer cares about, as the market shows them (the box / charger are extras). */
    private const SHOWN = ['screen', 'touch', 'body', 'biometrics', 'cameras', 'audio', 'charging', 'network', 'wifi', 'buttons', 'original_parts', 'box', 'charger'];

    public function forVariants(array $variantIds): array
    {
        // A shop that turned the module off doesn't show its devices' photos (nor, without them, the devices).
        if ($variantIds === [] || ! app(ModuleAccess::class)->enabled('used_devices')) {
            return [];
        }
        $out = [];
        $devices = UsedDevice::query()->with(['photos' => fn ($q) => $q->where('kind', 'device')->orderBy('id')])
            ->whereIn('variant_id', $variantIds)->where('status', UsedDevice::IN_STOCK)->get();
        foreach ($devices as $device) {
            /** @var UsedDevice $device */
            $checks = [];
            foreach (self::SHOWN as $key) {
                $value = $device->checklist[$key] ?? 'na';
                if ($value !== 'na') {
                    $checks[] = ['label' => Checklist::ITEMS[$key], 'ok' => $value === 'yes'];
                }
            }
            $out[$device->variant_id] = [
                'device_id' => $device->id,
                'grade' => $device->grade->value,
                'grade_label' => $device->grade->label(),
                'battery_health' => $device->battery_health,
                'storage' => $device->storage,
                'storage_gb' => self::gigabytes($device->storage),
                'color' => $device->color,
                'photos' => $device->photos->map(fn (UsedDevicePhoto $p) => PublicPhotos::url($device->id, $p->id))->values()->all(),
                'checks' => $checks,
                // The shop recorded the IMEI (and checked it's not already in stock) when buying it.
                'imei_registered' => true,
                'listed_at' => $device->bought_at->toIso8601String(),
            ];
        }

        return $out;
    }

    public function withoutPhotos(): int
    {
        return UsedDevice::query()->where('status', UsedDevice::IN_STOCK)
            ->whereDoesntHave('photos', fn ($q) => $q->where('kind', 'device'))->count();
    }

    public function changedSince(DateTimeInterface $since): array
    {
        $out = [];
        DB::table('used_devices')->where('updated_at', '>', $since)->orderBy('tenant_id')->select(['tenant_id', 'variant_id'])
            ->each(function (object $row) use (&$out): void {
                $out[$row->tenant_id][] = $row->variant_id;
            });

        return $out;
    }

    /** «128GB», «256 جيجا», «1TB» → 128, 256, 1024. */
    public static function gigabytes(?string $storage): ?int
    {
        if ($storage === null || ! preg_match('/(\d+(?:\.\d+)?)\s*(tb|t|تيرا)?/iu', $storage, $m)) {
            return null;
        }
        $gb = (int) round(((float) $m[1]) * (isset($m[2]) && $m[2] !== '' ? 1024 : 1));

        return $gb >= 8 && $gb <= 4096 ? $gb : null;
    }
}
