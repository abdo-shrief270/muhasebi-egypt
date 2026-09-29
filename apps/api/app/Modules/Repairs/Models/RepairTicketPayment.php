<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money on a ticket: the deposit at intake, the bill at delivery, or a deposit handed back.
 *
 * @property int $id
 * @property string $kind deposit | payment | refund
 * @property string $method cash | card | wallet | instapay | credit
 * @property int $amount
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'ticket_id', 'kind', 'method', 'amount', 'user_id', 'user_name', 'created_at'])]
final class RepairTicketPayment extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime'];
    }
}
