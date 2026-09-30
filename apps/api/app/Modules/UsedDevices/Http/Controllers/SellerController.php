<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Http\Controllers;

use App\Modules\UsedDevices\Actions\EraseSellerAction;
use App\Modules\UsedDevices\Http\Resources\UsedDeviceResource;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Modules\UsedDevices\Models\UsedDeviceSetting;
use App\Modules\UsedDevices\Support\NationalId;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * «الشخص ده باع لنا قبل كده؟»: sellers by national ID, phone or name, with what they sold (all
 * branches). Needs used_devices.view_seller. Erasure and the retention period are the owner's.
 */
final class SellerController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json(['data' => []]);
        }

        $digits = NationalId::normalize($q);
        $sellers = UsedDeviceSeller::query()
            ->withCount('devices')
            ->withMax('devices', 'bought_at')
            ->where(function (Builder $w) use ($q, $digits): void {
                if (preg_match('/^\d{14}$/', $digits)) {
                    $w->where('national_id_hash', NationalId::hashOf($digits));

                    return;
                }
                $phone = $this->phone($digits);
                $w->where('name', 'ilike', '%'.addcslashes($q, '%_\\').'%')
                    ->when($phone !== null, fn (Builder $x) => $x->orWhere('phone', $phone));
            })
            ->orderByDesc('devices_max_bought_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $sellers->map(fn (UsedDeviceSeller $s) => $this->summary($s))->values()->all()]);
    }

    public function show(string $seller): JsonResponse
    {
        $model = UsedDeviceSeller::query()->withCount('devices')->withMax('devices', 'bought_at')->findOrFail($seller);
        $devices = UsedDevice::query()->with('seller')->where('seller_id', $model->id)->orderByDesc('bought_at')->get();

        return response()->json(['data' => [
            ...$this->summary($model),
            'devices' => $devices->map(fn (UsedDevice $d) => new UsedDeviceResource($d))->all(),
        ]]);
    }

    public function erase(string $seller, EraseSellerAction $action): JsonResponse
    {
        $model = $action->handle(UsedDeviceSeller::query()->findOrFail($seller));

        return $this->show($model->id);
    }

    public function settings(): JsonResponse
    {
        return $this->settingsResponse(UsedDeviceSetting::query()->find($this->tenant->idOrFail()));
    }

    public function updateSettings(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate(
            ['id_retention_years' => ['required', 'integer', 'min:'.UsedDeviceSetting::MIN_YEARS, 'max:'.UsedDeviceSetting::MAX_YEARS]],
            attributes: ['id_retention_years' => 'مدة الاحتفاظ'],
        );
        $setting = UsedDeviceSetting::query()->firstOrNew(['tenant_id' => $this->tenant->idOrFail()]);
        $before = $setting->exists ? $setting->id_retention_years : UsedDeviceSetting::DEFAULT_YEARS;
        $years = (int) $data['id_retention_years'];
        $setting->fill(['id_retention_years' => $years, 'updated_by_name' => $request->user()?->getAttribute('name')])->save();

        if ($before !== $years) {
            $audit->record('used_devices.retention_changed', "خلّى بطايق بايعين المستعمل اللي اتمسحت بياناتهم تتحفظ {$years} سنين من آخر بيعة", $setting, ['id_retention_years' => [$before, $years]]);
        }

        return $this->settingsResponse($setting);
    }

    private function settingsResponse(?UsedDeviceSetting $setting): JsonResponse
    {
        return response()->json(['data' => [
            'id_retention_years' => $setting->id_retention_years ?? UsedDeviceSetting::DEFAULT_YEARS,
            'updated_by_name' => $setting?->updated_by_name,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
            'min_years' => UsedDeviceSetting::MIN_YEARS,
            'max_years' => UsedDeviceSetting::MAX_YEARS,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(UsedDeviceSeller $s): array
    {
        $last = $s->getAttribute('devices_max_bought_at');

        return [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'national_id' => $s->national_id,
            'birth_date' => $s->birth_date?->toDateString(),
            'gender' => $s->gender,
            'governorate' => $s->governorate,
            'erased' => $s->isErased(),
            'erased_at' => $s->erased_at?->toIso8601String(),
            'id_purged' => $s->id_purged_at !== null,
            'devices_count' => (int) $s->getAttribute('devices_count'),
            'last_sold_at' => $last ? Carbon::parse((string) $last)->toIso8601String() : null,
        ];
    }

    private function phone(string $digits): ?string
    {
        if (! preg_match('/^\+?\d{10,13}$/', $digits)) {
            return null;
        }
        try {
            return (new PhoneNumber($digits, 'EG'))->formatE164();
        } catch (\Throwable) {
            return null;
        }
    }
}
