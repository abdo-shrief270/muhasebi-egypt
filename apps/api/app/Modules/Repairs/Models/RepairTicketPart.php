<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A part taken from stock for a ticket.
 *
 * @property int $id
 * @property string $ticket_id
 * @property string $variant_id
 * @property string $name
 * @property int $qty
 * @property int $unit_price
 * @property int $unit_cost
 * @property string|null $added_by_name
 * @property list<string>|null $serials
 * @property int|null $list_price the price before it was changed on the ticket
 * @property string|null $price_note
 */
#[Fillable(['tenant_id', 'ticket_id', 'variant_id', 'name', 'qty', 'unit_price', 'unit_cost', 'added_by', 'added_by_name', 'serials', 'list_price', 'price_note', 'warranty_days'])]
final class RepairTicketPart extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['qty' => 'integer', 'unit_price' => 'integer', 'unit_cost' => 'integer', 'serials' => 'array', 'list_price' => 'integer', 'warranty_days' => 'integer'];
    }
}
