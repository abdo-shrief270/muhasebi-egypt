<?php

declare(strict_types=1);

namespace App\Modules\MultiBranch\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialCount;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\MultiBranch\Enums\TransferStatus;
use App\Modules\MultiBranch\Events\TransferShipped;
use App\Modules\MultiBranch\Models\StockTransfer;
use App\Modules\MultiBranch\Models\StockTransferItem;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Moving goods between a shop's branches. A transfer is requested (by either side), shipped by
 * the sending branch — stock and serials leave it at their FIFO cost — and received by the other,
 * at the same cost; what didn't arrive is recorded as short. Cancelling a shipped transfer puts
 * the goods back where they left.
 */
final class TransferActions
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly BranchDirectory $branches,
        private readonly DocumentNumbers $numbers,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  list<array{variant_id: string, qty: int, serials?: list<string>|null}>  $items
     * @param  bool  $shipNow  the sending branch sends it right away (the quantities and serials given)
     */
    public function create(Authenticatable $user, string $tenantId, string $fromId, string $toId, array $items, ?string $notes, bool $shipNow): StockTransfer
    {
        if ($fromId === $toId) {
            throw new DomainRuleException('اختار فرعين مختلفين.', 'transfer_same_branch');
        }
        $access = $this->branches->accessibleBranches($user);
        if (! isset($access[$fromId]) && ! isset($access[$toId])) {
            throw new DomainRuleException('مش معاك صلاحية على الفرعين دول.', 'branch_forbidden', 403);
        }
        $variants = $this->catalog->find(array_column($items, 'variant_id'));

        return DB::transaction(function () use ($user, $tenantId, $fromId, $toId, $items, $notes, $shipNow, $variants): StockTransfer {
            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'stock_transfer'),
                'from_branch_id' => $fromId,
                'to_branch_id' => $toId,
                'status' => TransferStatus::Requested,
                'notes' => $notes,
                'requested_by_name' => $user->getAttribute('name'),
            ]);
            foreach ($items as $item) {
                $variant = $variants[$item['variant_id']] ?? throw new DomainRuleException('فيه صنف مش موجود.', 'variant_not_found', 404);
                $transfer->items()->create([
                    'tenant_id' => $tenantId,
                    'variant_id' => $variant->id,
                    'name' => $variant->displayName(),
                    'track_serial' => $variant->trackSerial,
                    'qty_requested' => $item['qty'],
                ]);
            }
            if ($shipNow) {
                $transfer->load('items');
                $this->doShip($user, $transfer, array_map(fn (StockTransferItem $line, array $item) => [
                    'item_id' => $line->id, 'qty' => $item['qty'], 'serials' => $item['serials'] ?? null,
                ], $transfer->items->all(), $items));
            }

            return $transfer->load('items');
        });
    }

    /**
     * @param  list<array{item_id: string, qty: int, serials?: list<string>|null}>  $lines
     */
    public function ship(Authenticatable $user, StockTransfer $transfer, array $lines): StockTransfer
    {
        return DB::transaction(function () use ($user, $transfer, $lines): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->with('items')->findOrFail($transfer->id);
            $this->doShip($user, $transfer, $lines);

            return $transfer->load('items');
        });
    }

    /**
     * @param  list<array{item_id: string, qty: int, serials?: list<string>|null}>  $lines  what arrived
     */
    public function receive(Authenticatable $user, StockTransfer $transfer, array $lines): StockTransfer
    {
        return DB::transaction(function () use ($user, $transfer, $lines): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->with('items')->findOrFail($transfer->id);
            if ($transfer->status !== TransferStatus::Shipped) {
                throw new DomainRuleException("التحويل {$transfer->reference()} {$transfer->status->label()}.", 'transfer_status_invalid');
            }
            $this->ensureAccess($user, $transfer->to_branch_id, 'تستلم في');
            $byId = collect($lines)->keyBy('item_id');
            $reference = new StockReference(MovementType::TransferIn, 'stock_transfer', $transfer->id, note: $transfer->reference());
            $short = [];
            foreach ($transfer->items as $line) {
                if ($line->qty_shipped === 0) {
                    continue;
                }
                $given = $byId->get($line->id);
                $qty = (int) ($given['qty'] ?? $line->qty_shipped);
                if ($qty < 0 || $qty > $line->qty_shipped) {
                    throw new DomainRuleException("«{$line->name}»: المستلم أكتر من اللي اتبعت ({$line->qty_shipped}).", 'transfer_qty_invalid');
                }
                $serials = null;
                if ($line->track_serial) {
                    $serials = $this->serials->normalize($given['serials'] ?? ($qty === $line->qty_shipped ? $line->serials ?? [] : []));
                    SerialCount::check($line->name, true, $qty, $serials, $line->variant_id);
                    if (array_diff($serials, $line->serials ?? []) !== []) {
                        throw new DomainRuleException("«{$line->name}»: فيه سيريال مش من اللي اتبعتوا.", 'transfer_serial_unknown');
                    }
                }
                if ($qty > 0) {
                    $this->stock->receive($transfer->to_branch_id, $line->variant_id, $qty, $line->unit_cost, $reference);
                    if ($serials !== null) {
                        $this->serials->receive($transfer->to_branch_id, $line->variant_id, $serials, $reference);
                    }
                }
                $line->update(['qty_received' => $qty, 'received_serials' => $serials]);
                if ($qty < $line->qty_shipped) {
                    $short[] = "{$line->name} (ناقص ".($line->qty_shipped - $qty).')';
                }
            }
            $transfer->update(['status' => TransferStatus::Received, 'received_by_name' => $user->getAttribute('name'), 'received_at' => now()]);
            if ($short !== []) {
                $this->audit->record('transfers.short', "استلم التحويل {$transfer->reference()} ناقص: ".implode('، ', $short), $transfer);
            }

            return $transfer->load('items');
        });
    }

    /** Requested: just closed. Shipped: the goods go back into the branch they left, at their cost. */
    public function cancel(Authenticatable $user, StockTransfer $transfer, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($user, $transfer, $reason): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->with('items')->findOrFail($transfer->id);
            if (! in_array($transfer->status, [TransferStatus::Requested, TransferStatus::Shipped], true)) {
                throw new DomainRuleException("التحويل {$transfer->reference()} {$transfer->status->label()}.", 'transfer_status_invalid');
            }
            $access = $this->branches->accessibleBranches($user);
            if (! isset($access[$transfer->from_branch_id]) && ! isset($access[$transfer->to_branch_id])) {
                throw new DomainRuleException('مش معاك صلاحية على الفرعين دول.', 'branch_forbidden', 403);
            }
            if ($transfer->status === TransferStatus::Shipped) {
                $reference = new StockReference(MovementType::TransferIn, 'stock_transfer', $transfer->id, note: "إلغاء {$transfer->reference()}");
                foreach ($transfer->items as $line) {
                    if ($line->qty_shipped > 0) {
                        $this->stock->receive($transfer->from_branch_id, $line->variant_id, $line->qty_shipped, $line->unit_cost, $reference);
                        if ($line->track_serial && $line->serials) {
                            $this->serials->receive($transfer->from_branch_id, $line->variant_id, $line->serials, $reference);
                        }
                    }
                }
            }
            $transfer->update(['status' => TransferStatus::Cancelled, 'cancel_reason' => $reason]);
            $this->audit->record('transfers.cancelled', "لغى التحويل {$transfer->reference()}: {$reason}", $transfer);

            return $transfer->load('items');
        });
    }

    /**
     * @param  list<array{item_id: string, qty: int, serials?: list<string>|null}>  $lines
     */
    private function doShip(Authenticatable $user, StockTransfer $transfer, array $lines): void
    {
        if ($transfer->status !== TransferStatus::Requested) {
            throw new DomainRuleException("التحويل {$transfer->reference()} {$transfer->status->label()}.", 'transfer_status_invalid');
        }
        $this->ensureAccess($user, $transfer->from_branch_id, 'تبعت من');
        $byId = collect($lines)->keyBy('item_id');
        $available = $this->stock->quantities($transfer->from_branch_id, $transfer->items->pluck('variant_id')->all());
        $reference = new StockReference(MovementType::TransferOut, 'stock_transfer', $transfer->id, note: $transfer->reference());
        $units = 0;
        foreach ($transfer->items as $line) {
            $given = $byId->get($line->id);
            $qty = (int) ($given['qty'] ?? 0);
            if ($qty < 0) {
                throw new DomainRuleException("«{$line->name}»: الكمية غلط.", 'transfer_qty_invalid');
            }
            if ($qty > max(0, $available[$line->variant_id] ?? 0)) {
                throw new DomainRuleException("«{$line->name}» مفيش منه كفاية في الفرع (الموجود ".max(0, $available[$line->variant_id] ?? 0).').', 'out_of_stock', context: ['variant_id' => $line->variant_id]);
            }
            $serials = $qty > 0 ? SerialCount::check($line->name, $line->track_serial, $qty, $given['serials'] ?? null, $line->variant_id) : null;
            $unitCost = 0;
            if ($qty > 0) {
                $unitCost = $this->stock->issue($transfer->from_branch_id, $line->variant_id, $qty, $reference)->unitCost();
                if ($serials !== null) {
                    $serials = $this->serials->normalize($serials);
                    $this->serials->issue($transfer->from_branch_id, $line->variant_id, $serials, $reference);
                }
            }
            $line->update(['qty_shipped' => $qty, 'unit_cost' => $unitCost, 'serials' => $serials]);
            $units += $qty;
        }
        if ($units === 0) {
            throw new DomainRuleException('ابعت قطعة واحدة على الأقل.', 'transfer_empty');
        }
        $transfer->update(['status' => TransferStatus::Shipped, 'shipped_by_name' => $user->getAttribute('name'), 'shipped_at' => now()]);

        $names = $this->branches->all();
        $this->events->record(new TransferShipped(
            tenantId: $transfer->tenant_id,
            transferId: $transfer->id,
            reference: $transfer->reference(),
            fromBranch: $names[$transfer->from_branch_id] ?? '',
            toBranch: $names[$transfer->to_branch_id] ?? '',
            units: $units,
        ));
    }

    private function ensureAccess(Authenticatable $user, string $branchId, string $verb): void
    {
        if (! isset($this->branches->accessibleBranches($user)[$branchId])) {
            throw new DomainRuleException("مش معاك صلاحية {$verb} الفرع ده.", 'branch_forbidden', 403);
        }
    }
}
