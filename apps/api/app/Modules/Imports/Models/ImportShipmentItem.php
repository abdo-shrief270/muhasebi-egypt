<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $variant_id
 * @property string $name
 * @property bool $track_serial
 * @property int $qty
 * @property int $unit_price EGP piasters
 * @property int $received_qty good units into stock
 * @property int $damaged_qty arrived broken (not stocked)
 * @property int $landed_unit_cost
 * @property list<string>|null $serials
 */
#[Fillable(['tenant_id', 'shipment_id', 'variant_id', 'name', 'track_serial', 'qty', 'unit_price', 'received_qty', 'damaged_qty', 'landed_unit_cost', 'serials'])]
final class ImportShipmentItem extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'track_serial' => 'boolean',
            'qty' => 'integer',
            'unit_price' => 'integer',
            'received_qty' => 'integer',
            'damaged_qty' => 'integer',
            'landed_unit_cost' => 'integer',
            'serials' => 'array',
        ];
    }
}
