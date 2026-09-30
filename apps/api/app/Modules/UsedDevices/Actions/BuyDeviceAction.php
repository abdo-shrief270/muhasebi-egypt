<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Catalog\Contracts\UsedDeviceCatalog;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\UsedDevices\Enums\Grade;
use App\Modules\UsedDevices\Enums\PaymentMethod;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Modules\UsedDevices\Support\Checklist;
use App\Modules\UsedDevices\Support\Imei;
use App\Modules\UsedDevices\Support\NationalId;
use App\Modules\UsedDevices\Support\PhotoStore;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buys a used device from a walk-in seller: records who sold it (national ID, encrypted, and
 * photos of the card), the device and its inspection, pays the seller (cash out of the drawer, or
 * a wallet / bank transfer that doesn't touch it), and puts the device in stock as a unit of its
 * own — its own catalog variant at its asking price, received at what was paid, with its IMEI.
 */
final class BuyDeviceAction
{
    /** The youngest seller the shop may buy from. */
    public const MIN_SELLER_AGE = 18;

    public function __construct(
        private readonly UsedDeviceCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly CashDrawer $drawer,
        private readonly DocumentNumbers $numbers,
        private readonly PhotoStore $photos,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  array{name: string, phone: string|null, national_id: string}  $seller
     * @param  array{device_model_id: int|null, model_name: string|null, storage: string|null, color: string|null, imei: string, imei2: string|null,
     *               grade: Grade, checklist: array<string, string>, battery_health: int|null, notes: string|null,
     *               purchase_price: int, asking_price: int, payment_method: PaymentMethod}  $device
     * @param  array{id_front: UploadedFile, id_back: UploadedFile, device?: list<UploadedFile>}  $photos
     */
    public function handle(string $tenantId, string $branchId, array $seller, array $device, array $photos): UsedDevice
    {
        [$nationalId, $why] = NationalId::parse($seller['national_id']);
        if ($nationalId === null) {
            throw new DomainRuleException((string) $why, 'national_id_invalid', 422);
        }
        // No buying from minors (the owner's rule: a legal risk for the shop).
        if ($nationalId->age() < self::MIN_SELLER_AGE) {
            throw new DomainRuleException('البايع أقل من '.self::MIN_SELLER_AGE.' سنة؛ مينفعش نشتري منه.', 'seller_under_age', 422, ['age' => $nationalId->age()]);
        }

        $imei = Imei::normalize($device['imei']);
        $imei2 = $device['imei2'] !== null && trim($device['imei2']) !== '' ? Imei::normalize($device['imei2']) : null;
        foreach (array_filter([$imei, $imei2]) as $number) {
            if (! Imei::isValid($number)) {
                throw new DomainRuleException("الـ IMEI {$number} مش صحيح (لازم 15 رقم ورقم التحقق مظبوط). اطلب ‎*#06#‎ من الموبايل.", 'imei_invalid', 422, ['imei' => $number]);
            }
            if (($this->serials->history($number)['status'] ?? null) === 'in_stock') {
                throw new DomainRuleException("الجهاز ده (IMEI {$number}) موجود في مخزنك فعلاً.", 'imei_in_stock', 422, ['imei' => $number]);
            }
        }
        if ($imei2 === $imei) {
            throw new DomainRuleException('الـ IMEI التاني هو نفس الأول.', 'imei_duplicate', 422);
        }

        $checklist = Checklist::complete($device['checklist']);
        if ($checklist[Checklist::REQUIRED_YES] !== 'yes') {
            throw new DomainRuleException('لازم حساب iCloud / جوجل يتشال من الجهاز قبل ما تشتريه.', 'account_not_removed', 422);
        }

        $modelName = trim((string) $device['model_name']);
        if ($device['device_model_id'] !== null) {
            $model = $this->catalog->deviceModel($device['device_model_id']) ?? throw new DomainRuleException('الموديل مش موجود.', 'device_model_not_found', 404);
            $modelName = $model->fullName();
        }
        if ($modelName === '') {
            throw new DomainRuleException('اختار الموديل أو اكتبه.', 'model_required', 422);
        }

        $id = Str::uuid7()->toString();
        $stored = [];
        try {
            return DB::transaction(function () use ($id, $tenantId, $branchId, $seller, $device, $photos, $nationalId, $imei, $imei2, $checklist, $modelName, &$stored): UsedDevice {
                $user = $this->auth->guard('sanctum')->user();
                $person = $this->seller($seller, $nationalId);
                $number = $this->numbers->next($tenantId, 'used_device');
                $reference = 'UD-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);

                $unitName = implode(' · ', array_filter([$device['storage'], $device['color'], 'فئة '.$device['grade']->value, $reference]));
                $variant = $this->catalog->addUnit($device['device_model_id'], $modelName, $unitName, $device['asking_price']);

                $used = UsedDevice::create([
                    'id' => $id,
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'number' => $number,
                    'seller_id' => $person->id,
                    'device_model_id' => $device['device_model_id'],
                    'model_name' => $modelName,
                    'storage' => $device['storage'],
                    'color' => $device['color'],
                    'imei' => $imei,
                    'imei2' => $imei2,
                    'grade' => $device['grade'],
                    'checklist' => $checklist,
                    'battery_health' => $device['battery_health'],
                    'notes' => $device['notes'],
                    'purchase_price' => $device['purchase_price'],
                    'asking_price' => $device['asking_price'],
                    'payment_method' => $device['payment_method'],
                    'variant_id' => $variant->id,
                    'status' => UsedDevice::IN_STOCK,
                    'bought_by' => $user?->getAuthIdentifier(),
                    'bought_by_name' => $user?->getAttribute('name'),
                    'bought_at' => now(),
                ]);

                $stockRef = new StockReference(MovementType::UsedPurchase, refType: 'used_device', refId: $used->id, note: "شراء مستعمل {$reference}");
                $this->stock->receive($branchId, $variant->id, 1, $device['purchase_price'], $stockRef);
                $this->serials->receive($branchId, $variant->id, [$imei], $stockRef);

                if ($device['payment_method'] === PaymentMethod::Cash) {
                    $this->drawer->record($branchId, DrawerEntry::UsedDevicePurchase, 'cash', -$device['purchase_price'], 'used_device', $used->id, "{$reference} — {$used->title()}");
                }

                $files = [['id_front', $photos['id_front']], ['id_back', $photos['id_back']], ...array_map(fn (UploadedFile $f) => ['device', $f], $photos['device'] ?? [])];
                foreach ($files as [$kind, $file]) {
                    $path = $this->photos->put($tenantId, $used->id, $file);
                    $stored[] = $path;
                    $used->photos()->create([
                        'tenant_id' => $tenantId,
                        'kind' => $kind,
                        'path' => $path,
                        'mime' => (string) $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                        'created_at' => now(),
                    ]);
                }

                // The seller's name stays out of the description: it would outlive an erasure.
                $this->audit->record(
                    'used_devices.bought',
                    "اشترى جهاز مستعمل {$reference} ({$used->title()}) بـ ".number_format($device['purchase_price'] / 100, 2).' ج',
                    $used,
                    ['imei' => $imei, 'purchase_price' => $device['purchase_price'], 'asking_price' => $device['asking_price'], 'payment_method' => $device['payment_method']->value, 'returning_seller' => ! $person->wasRecentlyCreated],
                );

                return $used;
            });
        } catch (\Throwable $e) {
            $this->photos->delete($stored);

            throw $e;
        }
    }

    /**
     * The same person (by national ID) is one seller; their latest name and phone win. Someone
     * whose data was erased and who sells again is on record again.
     *
     * @param  array{name: string, phone: string|null, national_id: string}  $data
     */
    private function seller(array $data, NationalId $nationalId): UsedDeviceSeller
    {
        $seller = UsedDeviceSeller::query()->where('national_id_hash', $nationalId->hash())->lockForUpdate()->first()
            ?? new UsedDeviceSeller(['national_id_hash' => $nationalId->hash()]);

        $seller->fill([
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'national_id' => $nationalId->number,
            'birth_date' => $nationalId->birthDate->toDateString(),
            'gender' => $nationalId->gender,
            'governorate' => $nationalId->governorate(),
            'erased_at' => null,
        ])->save();

        return $seller;
    }
}
