<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\PriceChange;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Contracts\Auth\Factory as Auth;

/** Writes the price history; called inside the transaction that changes the price. */
final class PriceHistory
{
    public function __construct(private readonly Auth $auth) {}

    public function record(ProductVariant $variant, string $field, ?int $from, ?int $to, string $source, ?string $batchId = null): void
    {
        if ($from === $to) {
            return;
        }
        $user = $this->auth->guard('sanctum')->user();

        PriceChange::create([
            'tenant_id' => $variant->tenant_id,
            'variant_id' => $variant->id,
            'field' => $field,
            'old_price' => $from,
            'new_price' => $to,
            'source' => $source,
            'batch_id' => $batchId,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
