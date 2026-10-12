<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Models\MarketPriceConfirmation;
use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Illuminate\Support\Facades\DB;

/**
 * A new phone priced far from what the other shops ask is most often a typo (an extra zero, the
 * cost instead of the price): the market holds it back until the shop confirms that price.
 */
final class PriceChecks
{
    /** Needs at least this many other shops' prices to judge. */
    public const MIN_SHOPS = 3;

    public const LOW = 0.5;

    public const HIGH = 2.0;

    /** @var array<string, array{median: int, shops: int}>|null */
    private ?array $refs = null;

    public function __construct(private readonly SearchClient $search) {}

    /**
     * Why an offer is held back, or null. Runs in the shop's context.
     *
     * @param  array<string, int>  $confirmed  variant id => the price the shop confirmed
     */
    public function judge(string $variantId, ?string $itemId, bool $used, int $price, array $confirmed): ?array
    {
        if ($itemId === null || $used) {
            return null;
        }
        $ref = $this->refs()[$itemId] ?? null;
        if ($ref === null || $ref['shops'] < self::MIN_SHOPS || ($confirmed[$variantId] ?? null) === $price) {
            return null;
        }
        $ratio = $price / max(1, $ref['median']);

        return $ratio < self::LOW || $ratio > self::HIGH ? ['median' => $ref['median'], 'ratio' => round($ratio, 2)] : null;
    }

    /** @return array<string, int> the current shop's confirmations */
    public function confirmations(): array
    {
        return MarketPriceConfirmation::query()->pluck('price', 'variant_id')->map(fn ($p) => (int) $p)->all();
    }

    /** @return array<string, array{median: int, shops: int}> */
    private function refs(): array
    {
        return $this->refs ??= DB::table('market_price_refs')->get()
            ->mapWithKeys(fn (object $r) => [$r->item_id => ['median' => (int) $r->median, 'shops' => (int) $r->shops]])->all();
    }

    /**
     * The usual new price of every catalog phone on sale, from the index (held-back offers left
     * out), into market_price_refs (market:price-refs, hourly).
     */
    public function refresh(): int
    {
        $result = $this->search->search($this->search->name(MarketIndices::OFFERS), [
            'size' => 0,
            'query' => ['bool' => ['filter' => [['exists' => ['field' => 'item_id']], ['term' => ['condition' => 'new']]], 'must_not' => [['exists' => ['field' => 'flag']]]]],
            'aggs' => ['items' => [
                'terms' => ['field' => 'item_id', 'size' => 5000],
                'aggs' => [
                    'median' => ['percentiles' => ['field' => 'price', 'percents' => [50]]],
                    'shops' => ['cardinality' => ['field' => 'tenant_id']],
                ],
            ]],
        ]);
        $now = now();
        $rows = [];
        foreach ($result['aggregations']['items']['buckets'] ?? [] as $b) {
            $median = (int) round((float) (array_values($b['median']['values'] ?? [])[0] ?? 0));
            if ($median > 0) {
                $rows[] = ['item_id' => (string) $b['key'], 'median' => $median, 'shops' => (int) $b['shops']['value'], 'updated_at' => $now];
            }
        }
        DB::transaction(function () use ($rows): void {
            DB::table('market_price_refs')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('market_price_refs')->insert($chunk);
            }
        });
        $this->refs = null;

        return count($rows);
    }
}
