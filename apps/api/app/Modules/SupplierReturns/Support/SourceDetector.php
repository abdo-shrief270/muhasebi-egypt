<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Support;

use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\ShopOrders\Contracts\PartnerPurchases;
use App\Modules\SupplierReturns\Enums\SourceType;
use App\Modules\SupplierReturns\Models\ReturnNote;
use App\Modules\Suppliers\Contracts\SupplierAccounts;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;

/**
 * Finds where a unit came from:
 *  1. its serial (exact): the purchase / replacement it arrived on, or a partner shop's goods order with that IMEI;
 *  2. the stock lot it left (a purchase's lot, or a replacement a supplier sent back);
 *  3. otherwise the employee picks from the variant's possible sources (candidates()).
 */
final class SourceDetector
{
    public function __construct(
        private readonly SerialRegistry $serials,
        private readonly StockLedger $stock,
        private readonly SupplierAccounts $suppliers,
        private readonly PartnerPurchases $partners,
        private readonly ModuleAccess $modules,
    ) {}

    /**
     * @param  list<string>  $serials  normalised
     * @return array<string, array{source: Source, lot_hint: string|null}> keyed by serial; units with no known source left out
     */
    public function fromSerials(array $serials, string $variantId): array
    {
        $origins = $this->serials->origins($serials);
        $purchases = $this->suppliers->purchases(array_values(array_filter(array_map(
            fn (array $o): ?string => $o['type'] === MovementType::Purchase->value ? $o['ref_id'] : null,
            $origins,
        ))));
        $notes = $this->notes(array_values(array_filter(array_map(
            fn (array $o): ?string => $o['type'] === MovementType::SupplierReplacement->value ? $o['ref_id'] : null,
            $origins,
        ))));

        $found = [];
        foreach ($origins as $serial => $origin) {
            $source = match ($origin['type']) {
                MovementType::Purchase->value => $this->purchaseSource($purchases[$origin['ref_id']] ?? null),
                MovementType::SupplierReplacement->value => $notes[$origin['ref_id']] ?? null,
                default => null,
            };
            if ($source !== null && $origin['ref_id'] !== null) {
                $found[$serial] = [
                    'source' => $source->detectedBy(Source::SERIAL),
                    'lot_hint' => $this->stock->lotOf(MovementType::from($origin['type']), $origin['ref_id'], $variantId),
                ];
            }
        }

        $left = array_values(array_diff($serials, array_keys($found)));
        if ($left !== [] && $this->shopOrdersOn()) {
            foreach ($this->partners->bySerials($left) as $serial => $order) {
                $found[$serial] = [
                    'source' => new Source(SourceType::Shop, $order['tenant_id'], $order['name'], $order['phone'], $order['reference'], Source::SERIAL),
                    'lot_hint' => null,
                ];
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $lotIds
     * @return array<string, Source> keyed by lot id; lots with no known source (opening stock, returns…) left out
     */
    public function fromLots(array $lotIds): array
    {
        $origins = $this->stock->lotOrigins($lotIds);
        $purchases = $this->suppliers->purchases(array_values(array_filter(array_map(
            fn (array $o): ?string => $o['source_type'] === MovementType::Purchase->value ? $o['source_id'] : null,
            $origins,
        ))));
        $notes = $this->notes(array_values(array_filter(array_map(
            fn (array $o): ?string => $o['source_type'] === MovementType::SupplierReplacement->value ? $o['source_id'] : null,
            $origins,
        ))));

        $found = [];
        foreach ($origins as $lotId => $origin) {
            $source = match ($origin['source_type']) {
                MovementType::Purchase->value => $this->purchaseSource($purchases[$origin['source_id']] ?? null),
                MovementType::SupplierReplacement->value => $notes[$origin['source_id']] ?? null,
                default => null,
            };
            if ($source !== null) {
                $found[$lotId] = $source->detectedBy(Source::LOT);
            }
        }

        return $found;
    }

    /** A source the employee picked: a supplier of the shop, or a partner shop. */
    public function manual(string $type, string $id): Source
    {
        $sourceType = SourceType::tryFrom($type);
        if ($sourceType === SourceType::Supplier) {
            $supplier = $this->suppliers->find([$id])[$id] ?? null;
            if ($supplier !== null) {
                return new Source(SourceType::Supplier, $supplier['id'], $supplier['name'], $supplier['phone'], null, Source::MANUAL);
            }
        }
        if ($sourceType === SourceType::Shop && $this->shopOrdersOn()) {
            $shop = $this->partners->partners()[$id] ?? null;
            if ($shop !== null) {
                return new Source(SourceType::Shop, $shop['tenant_id'], $shop['name'], $shop['phone'], null, Source::MANUAL);
            }
        }

        throw new DomainRuleException('المصدر ده مش موجود.', 'source_not_found', 404);
    }

    /**
     * The variant's likely sources (who it was bought from lately) first, then every supplier and partner shop.
     *
     * @return array{suggested: list<array<string, mixed>>, suppliers: list<array<string, mixed>>, shops: list<array<string, mixed>>}
     */
    public function candidates(?string $variantId): array
    {
        $suggested = [];
        if ($variantId !== null) {
            foreach ($this->suppliers->recentPurchasesOf($variantId) as $p) {
                $suggested[] = ['type' => SourceType::Supplier->value, 'id' => $p['supplier_id'], 'name' => $p['supplier_name'], 'doc' => $p['reference'], 'date' => $p['date'], 'unit_cost' => $p['unit_cost']];
            }
        }
        $shops = [];
        if ($this->shopOrdersOn()) {
            foreach ($this->partners->recentSellers() as $o) {
                $suggested[] = ['type' => SourceType::Shop->value, 'id' => $o['tenant_id'], 'name' => $o['name'], 'doc' => $o['reference'], 'date' => $o['date'], 'unit_cost' => null];
            }
            $shops = array_values(array_map(fn (array $s): array => ['type' => SourceType::Shop->value, 'id' => $s['tenant_id'], 'name' => $s['name']], $this->partners->partners()));
        }

        return [
            'suggested' => $suggested,
            'suppliers' => array_map(fn (array $s): array => ['type' => SourceType::Supplier->value, 'id' => $s['id'], 'name' => $s['name']], $this->suppliers->active()),
            'shops' => $shops,
        ];
    }

    /** Fresh name / phone of a source (for a new note). */
    public function contact(SourceType $type, string $id): ?Source
    {
        try {
            return $this->manual($type->value, $id);
        } catch (DomainRuleException) {
            return null;
        }
    }

    /**
     * @param  array{supplier_id: string, supplier_name: string, reference: string}|null  $purchase
     */
    private function purchaseSource(?array $purchase): ?Source
    {
        return $purchase === null ? null : new Source(SourceType::Supplier, $purchase['supplier_id'], $purchase['supplier_name'], null, $purchase['reference']);
    }

    /**
     * Replacements a source sent back: the units come from that note's source.
     *
     * @param  list<string>  $noteIds
     * @return array<string, Source>
     */
    private function notes(array $noteIds): array
    {
        if ($noteIds === []) {
            return [];
        }

        return ReturnNote::query()->whereIn('id', array_values(array_unique($noteIds)))->get()
            ->mapWithKeys(fn (ReturnNote $n): array => [$n->id => new Source($n->source_type, $n->source_id, $n->source_name, $n->source_phone, $n->reference())])
            ->all();
    }

    private function shopOrdersOn(): bool
    {
        return $this->modules->enabled('shop_orders');
    }
}
