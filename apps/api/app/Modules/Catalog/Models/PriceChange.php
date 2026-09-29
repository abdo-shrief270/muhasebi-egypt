<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One change of a variant's selling price. Append-only.
 *
 * @property string $id
 * @property string $variant_id
 * @property string $field
 * @property int|null $old_price
 * @property int|null $new_price
 * @property string $source edit | bulk
 * @property string|null $batch_id
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'variant_id', 'field', 'old_price', 'new_price', 'source', 'batch_id', 'user_id', 'user_name', 'created_at'])]
final class PriceChange extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Price changes are append-only.'));
        self::deleting(fn () => throw new LogicException('Price changes are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'old_price' => 'integer',
            'new_price' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
