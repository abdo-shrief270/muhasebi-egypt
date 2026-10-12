<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Models\MarketLink;

/**
 * Which catalog phone each of a shop's offers is: a phone it sells (the shop's choice, else the
 * automatic match from its name), and for accessories / parts the phones they fit (from their
 * compatible models). Runs in the shop's context.
 */
final class PhoneLinks
{
    /** @var array<string, string|null> compatible model name => phone id */
    private array $models = [];

    public function __construct(
        private readonly PhoneMatcher $matcher,
        private readonly PhoneCatalog $catalog,
    ) {}

    /** A device in the catalog's sense: a phone sold new or used. */
    public static function isPhone(array $offer): bool
    {
        return $offer['used'] || $offer['category']['type'] === 'device';
    }

    /**
     * @param  list<array<string, mixed>>  $offers  from MarketCatalog::offers()
     * @return array<string, array{item_id: string|null, storage_gb: int|null, fits: list<string>, source: string}> variant id => link
     */
    public function resolve(array $offers): array
    {
        $phones = array_values(array_filter($offers, self::isPhone(...)));
        $chosen = $phones === [] ? collect() : MarketLink::query()->whereIn('variant_id', array_column($phones, 'variant_id'))->get()->keyBy('variant_id');

        $out = [];
        foreach ($offers as $offer) {
            $id = $offer['variant_id'];
            if (! self::isPhone($offer)) {
                $out[$id] = ['item_id' => null, 'storage_gb' => null, 'fits' => $this->fits($offer['models']), 'source' => 'none'];

                continue;
            }
            $link = $chosen->get($id);
            if ($link instanceof MarketLink) {
                $storage = $link->storage_gb;
                if ($storage === null && $link->phone_id !== null) {
                    $storage = PhoneMatcher::storage($offer['title'], $this->catalog->many([$link->phone_id])[$link->phone_id]['storage_gb'] ?? []);
                }
                $out[$id] = ['item_id' => $link->phone_id, 'storage_gb' => $link->phone_id ? $storage : null, 'fits' => [], 'source' => $link->phone_id ? 'shop' : 'none'];

                continue;
            }
            $match = $this->matcher->match($offer['title'], $offer['brand']);
            $out[$id] = $match === null
                ? ['item_id' => null, 'storage_gb' => null, 'fits' => [], 'source' => 'none']
                : ['item_id' => $match['phone']['id'], 'storage_gb' => $match['storage_gb'], 'fits' => [], 'source' => 'auto'];
        }

        return $out;
    }

    /**
     * @param  list<string>  $models  the item's compatible models («Apple iPhone 15»)
     * @return list<string> phone ids
     */
    private function fits(array $models): array
    {
        $ids = [];
        foreach ($models as $name) {
            if (! array_key_exists($name, $this->models)) {
                $this->models[$name] = $this->matcher->match($name)['phone']['id'] ?? null;
            }
            if ($this->models[$name] !== null) {
                $ids[] = $this->models[$name];
            }
        }

        return array_values(array_unique($ids));
    }
}
