<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Support\Tenancy\BelongsToTenant;
use App\Support\Text\SearchText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone model (iPhone 13, Galaxy A54, ...) that products can be compatible with.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $brand_id
 * @property string $name
 * @property string $search_name
 * @property-read Brand $brand
 */
#[Fillable(['tenant_id', 'brand_id', 'name'])]
final class DeviceModel extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        self::saving(function (DeviceModel $model): void {
            $brand = Brand::query()->whereKey($model->brand_id)->value('name');
            $model->search_name = SearchText::normalize("{$brand} {$model->name}");
        });
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** "Apple iPhone 13" */
    public function fullName(): string
    {
        return trim(($this->brand->name ?? '').' '.$this->name);
    }
}
