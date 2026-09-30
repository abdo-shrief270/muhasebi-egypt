<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Models;

use App\Modules\UsedDevices\Enums\Grade;
use App\Modules\UsedDevices\Enums\PaymentMethod;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A used device the shop bought (UD-00001). It is sold as its own catalog variant (variant_id),
 * found at the POS by its IMEI; status / sale_* follow its sale (SyncSoldDevices).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property int $number
 * @property string $seller_id
 * @property int|null $device_model_id
 * @property string $model_name
 * @property string|null $storage
 * @property string|null $color
 * @property string $imei
 * @property string|null $imei2
 * @property Grade $grade
 * @property array<string, string> $checklist
 * @property int|null $battery_health
 * @property string|null $notes
 * @property int $purchase_price
 * @property int $asking_price
 * @property PaymentMethod $payment_method
 * @property string $variant_id
 * @property string $status
 * @property string|null $sale_id
 * @property string|null $sale_reference
 * @property int|null $sale_price
 * @property Carbon|null $sold_at
 * @property string|null $bought_by
 * @property string|null $bought_by_name
 * @property Carbon $bought_at
 * @property-read UsedDeviceSeller $seller
 * @property-read Collection<int, UsedDevicePhoto> $photos
 */
#[Fillable([
    'tenant_id', 'branch_id', 'number', 'seller_id', 'device_model_id', 'model_name', 'storage', 'color', 'imei', 'imei2',
    'grade', 'checklist', 'battery_health', 'notes', 'purchase_price', 'asking_price', 'payment_method', 'variant_id',
    'status', 'sale_id', 'sale_reference', 'sale_price', 'sold_at', 'bought_by', 'bought_by_name', 'bought_at',
])]
final class UsedDevice extends Model
{
    use BelongsToTenant, HasUuids;

    public const IN_STOCK = 'in_stock';

    public const SOLD = 'sold';

    /** Left stock some other way (a stock count, a loss). */
    public const GONE = 'gone';

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'device_model_id' => 'integer',
            'grade' => Grade::class,
            'checklist' => 'array',
            'battery_health' => 'integer',
            'purchase_price' => 'integer',
            'asking_price' => 'integer',
            'payment_method' => PaymentMethod::class,
            'sale_price' => 'integer',
            'sold_at' => 'datetime',
            'bought_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UsedDeviceSeller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(UsedDeviceSeller::class, 'seller_id');
    }

    /**
     * @return HasMany<UsedDevicePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(UsedDevicePhoto::class)->orderBy('id');
    }

    public function reference(): string
    {
        return 'UD-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /** "Apple iPhone 13 128GB أسود" */
    public function title(): string
    {
        return trim(implode(' ', array_filter([$this->model_name, $this->storage, $this->color])));
    }

    public function profit(): ?int
    {
        return $this->sale_price === null ? null : $this->sale_price - $this->purchase_price;
    }

    public function daysInStock(): int
    {
        return (int) $this->bought_at->diffInDays($this->sold_at ?? now());
    }
}
