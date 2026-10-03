<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A cost of a shipment (shipping, customs, clearance…), spread over its items' landed cost.
 *
 * @property string $id
 * @property string $kind
 * @property string|null $contact_id who it's owed to
 * @property int $amount
 * @property string|null $note
 */
#[Fillable(['tenant_id', 'shipment_id', 'kind', 'contact_id', 'amount', 'note'])]
final class ImportShipmentCost extends Model
{
    use BelongsToTenant, HasUuids;

    public const KINDS = ['shipping' => 'شحن', 'customs' => 'جمارك', 'clearance' => 'تخليص', 'inland' => 'نقل داخلي', 'agent' => 'عمولة وسيط', 'other' => 'مصاريف تانية'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
