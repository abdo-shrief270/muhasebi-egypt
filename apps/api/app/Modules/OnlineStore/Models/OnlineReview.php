<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A customer's rating of the shop after a delivered order (one per order).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property int $rating 1–5
 * @property string|null $comment
 * @property string $customer_name first name only
 * @property bool $hidden
 * @property string $source store | market
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'order_id', 'rating', 'comment', 'customer_name', 'hidden', 'source'])]
final class OnlineReview extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['rating' => 'integer', 'hidden' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function toPublic(): array
    {
        return [
            'rating' => $this->rating,
            'comment' => $this->comment,
            'name' => $this->customer_name,
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
