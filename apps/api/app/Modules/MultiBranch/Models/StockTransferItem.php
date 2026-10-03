<?php

declare(strict_types=1);

namespace App\Modules\MultiBranch\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $variant_id
 * @property string $name
 * @property bool $track_serial
 * @property int $qty_requested
 * @property int $qty_shipped
 * @property int $qty_received
 * @property int $unit_cost
 * @property list<string>|null $serials
 * @property list<string>|null $received_serials
 */
#[Fillable(['tenant_id', 'transfer_id', 'variant_id', 'name', 'track_serial', 'qty_requested', 'qty_shipped', 'qty_received', 'unit_cost', 'serials', 'received_serials'])]
final class StockTransferItem extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'track_serial' => 'boolean',
            'qty_requested' => 'integer',
            'qty_shipped' => 'integer',
            'qty_received' => 'integer',
            'unit_cost' => 'integer',
            'serials' => 'array',
            'received_serials' => 'array',
        ];
    }
}
