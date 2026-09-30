<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Http\Resources;

use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Modules\UsedDevices\Support\Checklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A used device. Who sold it (and the photos of their card) only with used_devices.view_seller;
 * what was paid and the profit only with products.view_cost. $detail adds the checklist and photos.
 */
final class UsedDeviceResource extends JsonResource
{
    public function __construct(UsedDevice $device, private readonly bool $detail = false, private readonly ?bool $showSeller = null)
    {
        parent::__construct($device);
    }

    public function toArray(Request $request): array
    {
        /** @var UsedDevice $d */
        $d = $this->resource;
        $user = $request->user();
        $withSeller = $this->showSeller ?? (bool) $user?->can('used_devices.view_seller');
        $withCost = (bool) $user?->can('products.view_cost');

        $data = [
            'id' => $d->id,
            'reference' => $d->reference(),
            'branch_id' => $d->branch_id,
            'title' => $d->title(),
            'device_model_id' => $d->device_model_id,
            'model_name' => $d->model_name,
            'storage' => $d->storage,
            'color' => $d->color,
            'imei' => $d->imei,
            'imei2' => $d->imei2,
            'grade' => $d->grade->value,
            'grade_label' => $d->grade->label(),
            'battery_health' => $d->battery_health,
            'notes' => $d->notes,
            'asking_price' => $d->asking_price,
            'purchase_price' => $withCost ? $d->purchase_price : null,
            'payment_method' => $d->payment_method->value,
            'payment_method_label' => $d->payment_method->label(),
            'variant_id' => $d->variant_id,
            'status' => $d->status,
            'status_label' => match ($d->status) {
                UsedDevice::IN_STOCK => 'في المخزن',
                UsedDevice::SOLD => 'اتباع',
                default => 'خرج من المخزن',
            },
            'sale' => $d->sale_id === null ? null : [
                'id' => $d->sale_id,
                'reference' => $d->sale_reference,
                'price' => $d->sale_price,
                'sold_at' => $d->sold_at?->toIso8601String(),
            ],
            'sold_at' => $d->sold_at?->toIso8601String(),
            'profit' => $withCost ? $d->profit() : null,
            'days_in_stock' => $d->daysInStock(),
            'bought_at' => $d->bought_at->toIso8601String(),
            'bought_by_name' => $d->bought_by_name,
            'seller' => $withSeller && $d->relationLoaded('seller') ? $this->seller($d->seller) : null,
            'seller_hidden' => ! $withSeller,
        ];

        if ($this->detail) {
            $data['checklist'] = array_map(fn (array $item): array => [...$item, 'value' => $d->checklist[$item['key']] ?? 'na'], Checklist::items());
            $data['photos'] = $d->photos
                ->filter(fn (UsedDevicePhoto $p) => $withSeller || ! $p->isIdCard())
                ->map(fn (UsedDevicePhoto $p): array => ['id' => $p->id, 'kind' => $p->kind])
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function seller(UsedDeviceSeller $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'national_id' => $s->national_id,
            'birth_date' => $s->birth_date?->toDateString(),
            'age' => $s->birth_date ? (int) $s->birth_date->diffInYears(now()) : null,
            'gender' => $s->gender,
            'governorate' => $s->governorate,
            'erased' => $s->isErased(),
            'id_purged' => $s->id_purged_at !== null,
        ];
    }
}
