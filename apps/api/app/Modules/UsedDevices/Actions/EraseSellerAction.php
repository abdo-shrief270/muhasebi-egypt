<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Actions;

use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Support\Audit\Auditor;
use App\Support\Privacy\Anonymised;
use Illuminate\Support\Facades\DB;

/**
 * Right to erasure (Law 151/2020) for someone who sold the shop a device: their name and phone go
 * now. Their national ID and the photos of their card are the shop's anti-theft record, so they
 * stay (encrypted, visible only with used_devices.view_seller) for the shop's retention period
 * after their last sale, then PurgeSellerIdsAction deletes them.
 */
final class EraseSellerAction
{
    public const BY_OWNER = 'owner';

    public const BY_CUSTOMER_ERASURE = 'customer_erased';

    public function __construct(private readonly Auditor $audit) {}

    public function handle(UsedDeviceSeller $seller, string $reason = self::BY_OWNER): UsedDeviceSeller
    {
        return DB::transaction(function () use ($seller, $reason): UsedDeviceSeller {
            $locked = UsedDeviceSeller::query()->lockForUpdate()->findOrFail($seller->id);
            if ($locked->isErased()) {
                return $locked;
            }
            $locked->forceFill(['name' => Anonymised::NAME, 'phone' => null, 'erased_at' => now()])->save();

            $this->audit->record(
                'used_devices.seller_erased',
                $reason === self::BY_CUSTOMER_ERASURE
                    ? 'مسح اسم وموبايل بايع جهاز مستعمل (مع مسح بيانات العميل)'
                    : 'مسح اسم وموبايل بايع جهاز مستعمل (الرقم القومي وصور البطاقة محفوظين لحد نهاية مدة الاحتفاظ)',
                $locked,
                ['reason' => $reason],
            );

            return $locked;
        });
    }
}
