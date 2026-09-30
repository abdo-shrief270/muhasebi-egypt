<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\DeviceModelSummary;
use App\Modules\Catalog\Contracts\UsedDeviceCatalog;
use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Catalog\Enums\CategoryType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\PriceHistory;
use App\Support\Text\SearchText;
use Illuminate\Database\Eloquent\Builder;

final class UsedDeviceCatalogService implements UsedDeviceCatalog
{
    public const CATEGORY = 'موبايلات مستعملة';

    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly PriceHistory $history,
    ) {}

    public function deviceModels(?string $q, int $limit = 30): array
    {
        return DeviceModel::query()
            ->with('brand')
            ->when($q !== null && trim($q) !== '', function (Builder $query) use ($q): void {
                foreach (SearchText::tokens((string) $q) as $token) {
                    $query->where('search_name', 'like', SearchText::like($token));
                }
            })
            ->orderBy('search_name')
            ->limit($limit)
            ->get()
            ->map(fn (DeviceModel $m) => $this->summary($m))
            ->values()
            ->all();
    }

    public function deviceModel(int $id): ?DeviceModelSummary
    {
        $model = DeviceModel::query()->with('brand')->find($id);

        return $model === null ? null : $this->summary($model);
    }

    public function addUnit(?int $deviceModelId, string $modelName, string $unitName, int $price): VariantSummary
    {
        $model = $deviceModelId === null ? null : DeviceModel::query()->with('brand')->find($deviceModelId);
        $name = ($model?->fullName() ?? trim($modelName)).' مستعمل';

        $category = Category::query()->firstOrCreate(['name' => self::CATEGORY], ['type' => CategoryType::Device->value, 'sort' => 100]);
        $product = Product::query()->where('category_id', $category->id)->where('name', $name)->lockForUpdate()->first();
        if ($product === null) {
            $product = Product::create([
                'category_id' => $category->id,
                'brand_id' => $model?->brand_id,
                'name' => $name,
                'track_serial' => true,
                'notes' => 'أجهزة مستعملة: كل جهاز صنف لوحده بسعره وبالـ IMEI بتاعه.',
            ]);
            if ($model !== null) {
                $product->deviceModels()->attach($model->id);
            }
        } elseif (! $product->is_active || ! $product->track_serial) {
            $product->forceFill(['is_active' => true, 'track_serial' => true])->save();
        }

        $variant = $product->variants()->create([
            'tenant_id' => $product->tenant_id,
            'name' => $unitName,
            'price_retail' => $price,
            'sort' => ((int) $product->variants()->max('sort')) + 1,
        ]);

        return $this->variants->find([$variant->id])[$variant->id];
    }

    public function setUnitPrice(string $variantId, int $price): void
    {
        $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
        $old = $variant->price_retail;
        if ($old === $price) {
            return;
        }
        $variant->update(['price_retail' => $price]);
        $this->history->record($variant, 'price_retail', $old, $price, 'edit');
    }

    public function setUnitActive(string $variantId, bool $active): void
    {
        ProductVariant::query()->whereKey($variantId)->update(['is_active' => $active]);
    }

    private function summary(DeviceModel $m): DeviceModelSummary
    {
        return new DeviceModelSummary($m->id, $m->brand_id, (string) ($m->brand->name ?? ''), $m->name);
    }
}
