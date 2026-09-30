<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Actions;

use App\Modules\Catalog\Contracts\UsedDeviceCatalog;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Changes a device in stock: its asking price (the catalog price the POS sells at, with the price
 * history) and notes. What was paid for it is fixed: it is the cost of the stock received.
 */
final class UpdateDeviceAction
{
    public function __construct(
        private readonly UsedDeviceCatalog $catalog,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{asking_price?: int, notes?: string|null}  $data
     */
    public function handle(UsedDevice $device, array $data): UsedDevice
    {
        return DB::transaction(function () use ($device, $data): UsedDevice {
            $device = UsedDevice::query()->lockForUpdate()->findOrFail($device->id);
            $old = $device->asking_price;

            if (array_key_exists('asking_price', $data) && $data['asking_price'] !== $old) {
                if ($device->status !== UsedDevice::IN_STOCK) {
                    throw new DomainRuleException('الجهاز اتباع؛ سعره مينفعش يتغير.', 'device_not_in_stock');
                }
                $device->asking_price = $data['asking_price'];
                $this->catalog->setUnitPrice($device->variant_id, $data['asking_price']);
                $this->audit->record(
                    'used_devices.price_changed',
                    "غيّر سعر بيع الجهاز المستعمل {$device->reference()} من ".number_format($old / 100, 2).' لـ '.number_format($data['asking_price'] / 100, 2).' ج',
                    $device,
                    ['asking_price' => [$old, $data['asking_price']]],
                );
            }
            if (array_key_exists('notes', $data)) {
                $device->notes = $data['notes'];
            }
            $device->save();

            return $device;
        });
    }
}
