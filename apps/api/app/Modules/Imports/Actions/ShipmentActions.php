<?php

declare(strict_types=1);

namespace App\Modules\Imports\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Imports\Enums\ShipmentStatus;
use App\Modules\Imports\Models\ImportContact;
use App\Modules\Imports\Models\ImportShipment;
use App\Modules\Imports\Models\ImportShipmentCost;
use App\Modules\Imports\Support\ContactLedger;
use App\Modules\Imports\Support\LandedCost;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialCount;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * An import shipment's life. The supplier's statement carries the goods (and follows edits until
 * receipt); each cost carries its contact's. Receiving spreads the costs over the items (landed
 * cost), puts the good units into the branch's stock at it, and — when chosen — claims the missing
 * and damaged units back from the supplier.
 */
final class ShipmentActions
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly ContactLedger $ledger,
        private readonly DocumentNumbers $numbers,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{contact_id: string, branch_id: string, ordered_on: string, expected_on: ?string, allocation: string, original_amount: ?string, notes: ?string}  $header
     * @param  list<array{variant_id: string, qty: int, unit_price: int}>  $items
     */
    public function create(string $tenantId, array $header, array $items): ImportShipment
    {
        $this->ensureSupplier($header['contact_id']);

        return DB::transaction(function () use ($tenantId, $header, $items): ImportShipment {
            $shipment = ImportShipment::create([
                ...$header,
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'import_shipment'),
                'status' => ShipmentStatus::Ordered,
                'goods_total' => 0,
                'costs_total' => 0,
            ]);
            $this->replaceItems($shipment, $items);

            return $shipment->load(['items', 'costs']);
        });
    }

    /**
     * Header and items while it isn't received; the supplier's statement follows the change.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array{variant_id: string, qty: int, unit_price: int}>|null  $items
     */
    public function update(ImportShipment $shipment, array $header, ?array $items): ImportShipment
    {
        return DB::transaction(function () use ($shipment, $header, $items): ImportShipment {
            $shipment = $this->lockOpen($shipment);
            if (isset($header['contact_id']) && $header['contact_id'] !== $shipment->contact_id) {
                // Another supplier: the goods move from one statement to the other.
                $this->ensureSupplier($header['contact_id']);
                $this->ledger->post($shipment->contact_id, 'reversal', -$shipment->goods_total, 'import_shipment', $shipment->id, "{$shipment->reference()} اتنقلت لمورد تاني");
                $this->ledger->post($header['contact_id'], 'shipment', $shipment->goods_total, 'import_shipment', $shipment->id, $shipment->reference());
            }
            $shipment->update($header);
            if ($items !== null) {
                $this->replaceItems($shipment, $items);
            }

            return $shipment->load(['items', 'costs']);
        });
    }

    public function move(ImportShipment $shipment, ShipmentStatus $to): ImportShipment
    {
        if (! in_array($to, ShipmentStatus::onTheWay(), true)) {
            throw new DomainRuleException('الحالة دي ليها زرار لوحدها.', 'shipment_status_invalid');
        }

        return DB::transaction(function () use ($shipment, $to): ImportShipment {
            $shipment = $this->lockOpen($shipment);
            $shipment->update(['status' => $to]);

            return $shipment;
        });
    }

    public function addCost(ImportShipment $shipment, string $kind, int $amount, ?string $contactId, ?string $note): ImportShipment
    {
        return DB::transaction(function () use ($shipment, $kind, $amount, $contactId, $note): ImportShipment {
            $shipment = $this->lockOpen($shipment);
            $cost = $shipment->costs()->create(['tenant_id' => $shipment->tenant_id, 'kind' => $kind, 'amount' => $amount, 'contact_id' => $contactId, 'note' => $note]);
            if ($contactId !== null) {
                $this->ledger->post($contactId, 'cost', $amount, 'import_shipment', $shipment->id, ImportShipmentCost::KINDS[$kind]." — {$shipment->reference()}");
            }
            $shipment->update(['costs_total' => $shipment->costs_total + $cost->amount]);

            return $shipment->load(['items', 'costs']);
        });
    }

    public function removeCost(ImportShipment $shipment, ImportShipmentCost $cost): ImportShipment
    {
        return DB::transaction(function () use ($shipment, $cost): ImportShipment {
            $shipment = $this->lockOpen($shipment);
            abort_unless($cost->getAttribute('shipment_id') === $shipment->id, 404);
            if ($cost->contact_id !== null) {
                $this->ledger->post($cost->contact_id, 'reversal', -$cost->amount, 'import_shipment', $shipment->id, 'شيل '.ImportShipmentCost::KINDS[$cost->kind]." — {$shipment->reference()}");
            }
            $shipment->update(['costs_total' => $shipment->costs_total - $cost->amount]);
            $cost->delete();

            return $shipment->load(['items', 'costs']);
        });
    }

    /**
     * @param  list<array{item_id: string, received: int, damaged: int, serials?: list<string>|null}>  $lines
     * @param  bool  $claim  missing / damaged units are claimed from the supplier (off his statement)
     */
    public function receive(Authenticatable $user, ImportShipment $shipment, array $lines, bool $claim): ImportShipment
    {
        return DB::transaction(function () use ($user, $shipment, $lines, $claim): ImportShipment {
            $shipment = $this->lockOpen($shipment);
            $shipment->load('items');
            $given = collect($lines)->keyBy('item_id');
            $rows = [];
            foreach ($shipment->items as $item) {
                $line = $given->get($item->id) ?? ['received' => $item->qty, 'damaged' => 0, 'serials' => null];
                $received = (int) $line['received'];
                $damaged = (int) ($line['damaged'] ?? 0);
                if ($received < 0 || $damaged < 0 || $received + $damaged > $item->qty) {
                    throw new DomainRuleException("«{$item->name}»: الواصل والتالف أكتر من المطلوب ({$item->qty}).", 'shipment_qty_invalid');
                }
                $serials = $received > 0 ? SerialCount::check($item->name, $item->track_serial, $received, $line['serials'] ?? null, $item->variant_id) : null;
                $rows[] = ['item' => $item, 'received' => $received, 'damaged' => $damaged, 'serials' => $serials];
            }
            if (array_sum(array_column($rows, 'received')) === 0) {
                throw new DomainRuleException('مفيش ولا قطعة سليمة وصلت؟ لو الشحنة كلها راحت، الغيها.', 'shipment_nothing_received');
            }

            $landed = LandedCost::compute(
                array_map(fn (array $r) => ['qty' => $r['item']->qty, 'unit_price' => $r['item']->unit_price, 'received' => $r['received']], $rows),
                $shipment->costs_total,
                $shipment->allocation,
                $claim,
            );
            $reference = new StockReference(MovementType::Import, 'import_shipment', $shipment->id, note: $shipment->reference());
            $claimed = 0;
            $short = [];
            foreach ($rows as $i => $r) {
                $item = $r['item'];
                $unitCost = $landed[$i]['unit_cost'];
                if ($r['received'] > 0) {
                    $this->stock->receive($shipment->branch_id, $item->variant_id, $r['received'], $unitCost, $reference);
                    if ($r['serials'] !== null) {
                        $this->serials->receive($shipment->branch_id, $item->variant_id, $this->serials->normalize($r['serials']), $reference);
                    }
                }
                $item->update([
                    'received_qty' => $r['received'],
                    'damaged_qty' => $r['damaged'],
                    'landed_unit_cost' => $unitCost,
                    'serials' => $r['serials'] !== null ? $this->serials->normalize($r['serials']) : null,
                ]);
                $missing = $item->qty - $r['received'];
                if ($missing > 0) {
                    $parts = array_filter([$r['damaged'] > 0 ? "{$r['damaged']} تالف" : null, $missing > $r['damaged'] ? ($missing - $r['damaged']).' ناقص' : null]);
                    $short[] = "{$item->name}: ".implode('، ', $parts);
                    $claimed += $missing * $item->unit_price;
                }
            }
            if ($claim && $claimed > 0) {
                $this->ledger->post($shipment->contact_id, 'claim', -$claimed, 'import_shipment', $shipment->id, "نواقص وتالف {$shipment->reference()}");
            }
            $shipment->update(['status' => ShipmentStatus::Received, 'received_at' => now(), 'received_by_name' => $user->getAttribute('name')]);
            if ($short !== []) {
                $this->audit->record('imports.short', "استلام {$shipment->reference()} ناقص: ".implode(' · ', $short).($claim ? ' (اتطالب بيهم المورد)' : ''), $shipment);
            }

            return $shipment->load(['items', 'costs']);
        });
    }

    /** Before receipt: the supplier's goods and every cost come off the statements. */
    public function cancel(ImportShipment $shipment, string $reason): ImportShipment
    {
        return DB::transaction(function () use ($shipment, $reason): ImportShipment {
            $shipment = $this->lockOpen($shipment);
            $shipment->load('costs');
            $this->ledger->post($shipment->contact_id, 'reversal', -$shipment->goods_total, 'import_shipment', $shipment->id, "إلغاء {$shipment->reference()}");
            foreach ($shipment->costs as $cost) {
                if ($cost->contact_id !== null) {
                    $this->ledger->post($cost->contact_id, 'reversal', -$cost->amount, 'import_shipment', $shipment->id, "إلغاء {$shipment->reference()}");
                }
            }
            $shipment->update(['status' => ShipmentStatus::Cancelled, 'cancel_reason' => $reason]);
            $this->audit->record('imports.cancelled', "لغى الشحنة {$shipment->reference()}: {$reason}", $shipment);

            return $shipment->load(['items', 'costs']);
        });
    }

    /** @param  list<array{variant_id: string, qty: int, unit_price: int}>  $items */
    private function replaceItems(ImportShipment $shipment, array $items): void
    {
        $variants = $this->catalog->find(array_column($items, 'variant_id'));
        $shipment->items()->delete();
        $total = 0;
        foreach ($items as $item) {
            $variant = $variants[$item['variant_id']] ?? throw new DomainRuleException('فيه صنف مش موجود.', 'variant_not_found', 404);
            $shipment->items()->create([
                'tenant_id' => $shipment->tenant_id,
                'variant_id' => $variant->id,
                'name' => $variant->displayName(),
                'track_serial' => $variant->trackSerial,
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
            ]);
            $total += $item['qty'] * $item['unit_price'];
        }
        // The supplier's statement carries the goods' value: post only the difference.
        $this->ledger->post($shipment->contact_id, 'shipment', $total - $shipment->goods_total, 'import_shipment', $shipment->id, $shipment->goods_total === 0 ? $shipment->reference() : "تعديل {$shipment->reference()}");
        $shipment->update(['goods_total' => $total]);
    }

    private function lockOpen(ImportShipment $shipment): ImportShipment
    {
        $shipment = ImportShipment::query()->lockForUpdate()->findOrFail($shipment->id);
        if (! $shipment->status->isOpen()) {
            throw new DomainRuleException("الشحنة {$shipment->reference()} {$shipment->status->label()}.", 'shipment_closed');
        }

        return $shipment;
    }

    private function ensureSupplier(string $contactId): void
    {
        $contact = ImportContact::query()->find($contactId) ?? throw new DomainRuleException('الجهة دي مش موجودة.', 'contact_not_found', 404);
        if (! in_array($contact->type, ['supplier', 'agent'], true)) {
            throw new DomainRuleException('الشحنة بتبقى من مصنع / تاجر أو وسيط.', 'contact_not_supplier');
        }
    }
}
