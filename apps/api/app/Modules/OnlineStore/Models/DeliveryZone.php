<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Where the shop delivers and for how much (piasters), e.g. «مدينة نصر» 30 ج.
 *
 * @property string $id
 * @property string $name
 * @property int $fee
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['tenant_id', 'name', 'fee', 'is_active', 'sort'])]
final class DeliveryZone extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'online_delivery_zones';

    protected function casts(): array
    {
        return ['fee' => 'integer', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    /** @return array{id: string, name: string, fee: int, is_active: bool} */
    public function toApi(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'fee' => $this->fee, 'is_active' => $this->is_active];
    }
}
