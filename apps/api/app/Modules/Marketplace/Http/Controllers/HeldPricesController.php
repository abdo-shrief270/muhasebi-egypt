<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Catalog\Contracts\MarketCatalog;
use App\Modules\Marketplace\Jobs\SyncShopVariants;
use App\Modules\Marketplace\Models\MarketPriceConfirmation;
use App\Modules\Marketplace\Support\MarketIndexer;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * «أسعار محتاجة تأكيد» (marketplace.manage): new phones the market holds back because their price
 * is far from what the other shops ask; the shop fixes the price, or confirms it.
 */
final class HeldPricesController
{
    public function index(MarketIndexer $indexer): JsonResponse
    {
        return response()->json(['data' => $indexer->heldPrices()]);
    }

    public function confirm(string $variant, MarketCatalog $catalog, CurrentTenant $tenant, Auditor $audit): JsonResponse
    {
        $offer = $catalog->offers([$variant])[0] ?? null;
        abort_if($offer === null, 404);
        DB::transaction(function () use ($variant, $offer, $tenant, $audit): void {
            $row = MarketPriceConfirmation::query()->updateOrCreate(['variant_id' => $variant], ['tenant_id' => $tenant->idOrFail(), 'price' => $offer['price']]);
            $audit->record('marketplace.price_confirmed', "أكّد سعر «{$offer['title']}» (".number_format($offer['price'] / 100).' ج) في سوق محاسبي', $row);
        });
        SyncShopVariants::dispatch($tenant->idOrFail(), [$variant])->afterCommit();

        return response()->json(['data' => ['ok' => true]]);
    }
}
