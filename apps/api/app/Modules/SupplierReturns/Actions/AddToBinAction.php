<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialCount;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockPortion;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Modules\SupplierReturns\Enums\SourceType;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Modules\SupplierReturns\Support\Source;
use App\Modules\SupplierReturns\Support\SourceDetector;
use App\Modules\Suppliers\Contracts\SupplierAccounts;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts units in the returns bin, each with its source (serial → lot → the employee's pick):
 *  - fromStock(): taken out of sellable stock («طلّع للمرتجعات»): issued with MovementType::ReturnsBin
 *    (serials set aside as damaged);
 *  - fromDocument(): units already out of stock (a customer's damaged return, a defective repair part):
 *    no stock moves, the lots the document took them from tell where they came from.
 * The bin row keeps the lot and the cost, so a refused unit can go back into the lot it left.
 */
final class AddToBinAction
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly VariantCatalog $catalog,
        private readonly SourceDetector $detector,
        private readonly SupplierAccounts $suppliers,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  list<string>|null  $serials
     * @param  array{type: string, id: string}|null  $source  the employee's pick; detected when null
     * @return list<BinItem>
     */
    public function fromStock(string $tenantId, string $branchId, string $variantId, int $qty, ?array $serials, ReturnReason $reason, ?string $note, ?array $source = null): array
    {
        $variant = $this->catalog->find([$variantId])[$variantId] ?? throw new DomainRuleException('الصنف مش موجود.', 'variant_not_found', 404);
        $serials = SerialCount::check($variant->displayName(), $variant->trackSerial, $qty, $serials, $variantId);
        $serials = $serials === null ? null : $this->serials->normalize($serials);
        $picked = $source === null ? null : $this->detector->manual($source['type'], $source['id']);
        if ($reason === ReturnReason::Other && trim((string) $note) === '') {
            throw new DomainRuleException('اكتب السبب في الملاحظة.', 'note_required');
        }

        return DB::transaction(function () use ($tenantId, $branchId, $variantId, $variant, $qty, $serials, $reason, $note, $picked): array {
            $base = $this->base($tenantId, $branchId, $variantId, $variant->displayName(), $reason, $note, 'manual', null, null);
            $pickedLot = $this->pickedLot($picked, $variantId);
            $rows = [];

            if ($serials !== null) {
                $bySerial = $this->detector->fromSerials($serials, $variantId);
                foreach ($serials as $serial) {
                    $id = (string) Str::uuid7();
                    $reference = new StockReference(MovementType::ReturnsBin, refType: 'returns_bin', refId: $id, note: 'سلة المرتجعات — '.$reason->label());
                    $detected = $bySerial[$serial] ?? null;
                    $issue = $this->stock->issue($branchId, $variantId, 1, $reference, fromLotId: $picked ? $pickedLot : ($detected['lot_hint'] ?? null));
                    $this->serials->setAside($branchId, $variantId, [$serial], $reference);
                    $portion = $issue->portions[0];
                    $found = $picked ?? $detected['source'] ?? $this->lotSource($portion->lotId);
                    $rows[] = $this->row($base, $id, 1, $serial, $portion->unitCost, $portion->lotId, $found);
                }

                return $rows;
            }

            $id = (string) Str::uuid7();
            $reference = new StockReference(MovementType::ReturnsBin, refType: 'returns_bin', refId: $id, note: 'سلة المرتجعات — '.$reason->label());
            $issue = $this->stock->issue($branchId, $variantId, $qty, $reference, fromLotId: $pickedLot);
            $sources = $this->detector->fromLots(array_values(array_filter(array_map(fn (StockPortion $p) => $p->lotId, $issue->portions))));
            foreach ($issue->portions as $i => $portion) {
                $found = $picked ?? ($portion->lotId !== null ? ($sources[$portion->lotId] ?? null) : null);
                $rows[] = $this->row($base, $i === 0 ? $id : (string) Str::uuid7(), $portion->qty, null, $portion->unitCost, $portion->lotId, $found);
            }

            return $rows;
        });
    }

    /**
     * Units a document already took out of stock (a sale, a repair ticket) come back damaged.
     *
     * @param  string  $issuedRefType  the stock reference the document issued with ('sale', 'repair_ticket')
     * @param  list<string>|null  $serials  normalised
     * @return list<BinItem>
     */
    public function fromDocument(
        string $tenantId,
        string $branchId,
        string $variantId,
        int $qty,
        int $unitCost,
        ?array $serials,
        ?ReturnReason $reason,
        string $origin,
        string $originId,
        string $originLabel,
        string $issuedRefType,
        string $issuedRefId,
    ): array {
        $variant = $this->catalog->find([$variantId])[$variantId] ?? null;
        $reason ??= ReturnReason::ManufacturingDefect;

        return DB::transaction(function () use ($tenantId, $branchId, $variantId, $variant, $qty, $unitCost, $serials, $reason, $origin, $originId, $originLabel, $issuedRefType, $issuedRefId): array {
            $base = $this->base($tenantId, $branchId, $variantId, $variant?->displayName() ?? '—', $reason, null, $origin, $originId, $originLabel);
            $portions = $this->stock->issuedFor($issuedRefType, $issuedRefId, $variantId);
            $lotSources = $this->detector->fromLots(array_values(array_filter(array_map(fn (StockPortion $p) => $p->lotId, $portions))));
            $rows = [];

            if ($serials !== null && $serials !== []) {
                $bySerial = $this->detector->fromSerials($serials, $variantId);
                // Which lot each serial left is not recorded; the first one the document used stands in.
                $firstLot = $portions[0]->lotId ?? null;
                foreach ($serials as $serial) {
                    $detected = $bySerial[$serial] ?? null;
                    $lot = $detected['lot_hint'] ?? $firstLot;
                    $found = $detected['source'] ?? ($firstLot !== null ? ($lotSources[$firstLot] ?? null) : null);
                    $rows[] = $this->row($base, (string) Str::uuid7(), 1, $serial, $unitCost, $lot, $found);
                }

                return $rows;
            }

            // Spread the units over the lots the document took from, in order.
            $left = $qty;
            foreach ($portions as $portion) {
                if ($left === 0) {
                    break;
                }
                $take = min($left, $portion->qty);
                $found = $portion->lotId !== null ? ($lotSources[$portion->lotId] ?? null) : null;
                $rows[] = $this->row($base, (string) Str::uuid7(), $take, null, $portion->lotId !== null ? $portion->unitCost : $unitCost, $portion->lotId, $found);
                $left -= $take;
            }
            if ($left > 0) {
                $rows[] = $this->row($base, (string) Str::uuid7(), $left, null, $unitCost, null, null);
            }

            return $rows;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function base(string $tenantId, string $branchId, string $variantId, string $name, ReturnReason $reason, ?string $note, string $origin, ?string $originId, ?string $originLabel): array
    {
        return [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'variant_name' => $name,
            'reason' => $reason,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'origin' => $origin,
            'origin_id' => $originId,
            'origin_label' => $originLabel,
            'status' => BinItem::IN_BIN,
            'created_by_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function row(array $base, string $id, int $qty, ?string $serial, int $unitCost, ?string $lotId, ?Source $source): BinItem
    {
        $item = new BinItem([
            ...$base,
            'qty' => $qty,
            'serial' => $serial,
            'unit_cost' => $unitCost,
            'lot_id' => $lotId,
            ...($source?->columns() ?? []),
        ]);
        $item->id = $id;
        $item->save();

        return $item;
    }

    private function lotSource(?string $lotId): ?Source
    {
        return $lotId === null ? null : ($this->detector->fromLots([$lotId])[$lotId] ?? null);
    }

    /** A supplier the employee picked: take the units from that supplier's latest lot of the variant. */
    private function pickedLot(?Source $picked, string $variantId): ?string
    {
        if ($picked?->type !== SourceType::Supplier) {
            return null;
        }
        foreach ($this->suppliers->recentPurchasesOf($variantId, 20) as $purchase) {
            if ($purchase['supplier_id'] === $picked->id) {
                return $purchase['lot_id'];
            }
        }

        return null;
    }
}
