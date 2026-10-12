<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Catalog\Contracts\MarketCatalog;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Jobs\SyncShopVariants;
use App\Modules\Marketplace\Models\MarketLink;
use App\Modules\Marketplace\Support\PhoneLinks;
use App\Modules\Marketplace\Support\PhoneMatcher;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Text\SearchText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «اربط موبايلاتك بالكتالوج» (marketplace.manage): the phones the shop sells, which catalog phone
 * each one is (matched from its name, or the shop's choice), so they join «سعره في كل المحلات».
 */
final class MarketLinksController
{
    public const STATES = ['auto', 'shop', 'unmatched', 'excluded'];

    public function __construct(
        private readonly MarketCatalog $catalog,
        private readonly PhoneCatalog $phones,
        private readonly PhoneMatcher $matcher,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $p = $request->validate([
            'state' => ['nullable', Rule::in(self::STATES)],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $offers = array_values(array_filter($this->catalog->offers(), PhoneLinks::isPhone(...)));
        $links = MarketLink::query()->whereIn('variant_id', array_column($offers, 'variant_id'))->get()->keyBy('variant_id');
        $chosen = $this->phones->many($links->pluck('phone_id')->filter()->values()->all());

        $rows = [];
        foreach ($offers as $offer) {
            $link = $links->get($offer['variant_id']);
            $suggestions = [];
            if ($link instanceof MarketLink) {
                $phone = $link->phone_id !== null ? ($chosen[$link->phone_id] ?? null) : null;
                $state = $phone !== null ? 'shop' : 'excluded';
                $storage = $phone !== null ? ($link->storage_gb ?? PhoneMatcher::storage($offer['title'], $phone['storage_gb'])) : null;
            } else {
                $match = $this->matcher->match($offer['title'], $offer['brand']);
                $phone = $match['phone'] ?? null;
                $state = $phone !== null ? 'auto' : 'unmatched';
                $storage = $match['storage_gb'] ?? null;
                if ($phone === null) {
                    $suggestions = array_map(self::card(...), $this->matcher->suggest($offer['title'], $offer['brand']));
                }
            }
            $rows[] = [
                'variant_id' => $offer['variant_id'],
                'product_id' => $offer['product_id'],
                'title' => $offer['title'],
                'brand' => $offer['brand'],
                'used' => $offer['used'],
                'price' => $offer['price'],
                'image' => $offer['image'][320] ?? null,
                'state' => $state,
                'phone' => $phone !== null ? self::card($phone) : null,
                'storage_gb' => $storage,
                'storage_set' => $link?->storage_gb !== null,
                'suggestions' => $suggestions,
            ];
        }

        $counts = array_fill_keys(self::STATES, 0);
        foreach ($rows as $row) {
            $counts[$row['state']]++;
        }
        $words = SearchText::tokens((string) ($p['q'] ?? ''));
        $rows = array_values(array_filter($rows, fn (array $r) => (($p['state'] ?? null) === null || $r['state'] === $p['state'])
            && array_filter($words, fn (string $w) => ! str_contains(SearchText::normalize($r['title'].' '.($r['phone']['name'] ?? '')), $w)) === []));
        // What needs the shop first: no match, then the automatic ones to check.
        $order = ['unmatched' => 0, 'auto' => 1, 'shop' => 2, 'excluded' => 3];
        usort($rows, fn (array $a, array $b) => [$order[$a['state']], $a['title']] <=> [$order[$b['state']], $b['title']]);

        return response()->json(['data' => ['items' => $rows, 'counts' => $counts, 'total' => array_sum($counts)]]);
    }

    /** The catalog's phones for the picker. */
    public function catalog(Request $request): JsonResponse
    {
        $p = $request->validate(['q' => ['required', 'string', 'min:1', 'max:80']]);
        $found = $this->phones->browse(['q' => $p['q'], 'status' => 'published'])['phones'];

        return response()->json(['data' => array_map(self::card(...), array_slice($found, 0, 20))]);
    }

    public function update(Request $request, string $variant, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'phone_id' => ['present', 'nullable', 'uuid'],
            'storage_gb' => ['nullable', 'integer', Rule::in([16, 32, 64, 128, 256, 512, 1024, 2048])],
        ]);
        $offer = $this->offer($variant);
        $phone = $data['phone_id'] !== null ? ($this->phones->many([$data['phone_id']])[$data['phone_id']] ?? null) : null;
        if ($data['phone_id'] !== null && ($phone === null || $phone['status'] !== 'published')) {
            throw new DomainRuleException('الموبايل ده مش في الكتالوج.', 'phone_not_found');
        }

        DB::transaction(function () use ($variant, $data, $phone, $offer, $audit): void {
            $link = MarketLink::query()->updateOrCreate(
                ['variant_id' => $variant],
                ['tenant_id' => $this->tenant->idOrFail(), 'phone_id' => $phone['id'] ?? null, 'storage_gb' => $phone !== null ? ($data['storage_gb'] ?? null) : null],
            );
            $audit->record('marketplace.phone_link', $phone !== null
                ? "ربط «{$offer['title']}» بـ «{$phone['name']}» في سوق محاسبي"
                : "قال إن «{$offer['title']}» مش موبايل من كتالوج سوق محاسبي", $link);
        });
        SyncShopVariants::dispatch($this->tenant->idOrFail(), [$variant])->afterCommit();

        return response()->json(['data' => ['ok' => true]]);
    }

    /** Back to the automatic match. */
    public function destroy(string $variant): JsonResponse
    {
        $this->offer($variant);
        MarketLink::query()->where('variant_id', $variant)->delete();
        SyncShopVariants::dispatch($this->tenant->idOrFail(), [$variant]);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Accepts every automatic match the shop has checked at once.
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['variant_ids' => ['required', 'array', 'max:500'], 'variant_ids.*' => ['uuid']]);
        $offers = array_values(array_filter($this->catalog->offers(array_values(array_unique($data['variant_ids']))), PhoneLinks::isPhone(...)));
        $done = [];
        DB::transaction(function () use ($offers, &$done): void {
            foreach ($offers as $offer) {
                $match = $this->matcher->match($offer['title'], $offer['brand']);
                if ($match === null || MarketLink::query()->where('variant_id', $offer['variant_id'])->exists()) {
                    continue;
                }
                MarketLink::query()->create(['tenant_id' => $this->tenant->idOrFail(), 'variant_id' => $offer['variant_id'], 'phone_id' => $match['phone']['id'], 'storage_gb' => $match['storage_gb']]);
                $done[] = $offer['variant_id'];
            }
        });
        if ($done !== []) {
            SyncShopVariants::dispatch($this->tenant->idOrFail(), $done)->afterCommit();
        }

        return response()->json(['data' => ['confirmed' => count($done)]]);
    }

    /** @return array<string, mixed> one of the shop's phones on sale */
    private function offer(string $variant): array
    {
        $offer = preg_match('/^[0-9a-f-]{36}$/i', $variant) ? ($this->catalog->offers([$variant])[0] ?? null) : null;
        abort_if($offer === null || ! PhoneLinks::isPhone($offer), 404);

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $phone
     * @return array<string, mixed>
     */
    private static function card(array $phone): array
    {
        return [
            'id' => $phone['id'],
            'slug' => $phone['slug'],
            'name' => $phone['name'],
            'year' => $phone['year'] ?? null,
            'storage_gb' => $phone['storage_gb'] ?? [],
        ];
    }
}
