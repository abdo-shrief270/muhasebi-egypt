<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\SupplierReturns\Enums\NoteStatus;
use App\Modules\SupplierReturns\Enums\SourceType;
use App\Modules\SupplierReturns\Events\ReturnNoteSettled;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Modules\SupplierReturns\Models\ReturnNote;
use App\Modules\Suppliers\Contracts\SupplierAccounts;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The source answered: per line, how many units it accepted (partial acceptance). Accepted units
 * leave the shop and are settled one way for the whole note:
 *  - credit: off what the shop owes the supplier (SupplierAccounts);
 *  - refund: the money comes into the user's drawer (CashDrawer; a bank transfer doesn't) and the
 *    supplier's statement shows the return and the refund;
 *  - replacement: the same number of units comes back into stock at the same cost (new serials).
 * Refused units go back to sellable stock (into the lot they left) or are written off.
 * A partner shop source: only this shop's side is recorded (ReturnNoteSettled is the hook).
 */
final class SettleNoteAction
{
    public const RESOLUTIONS = ['credit', 'refund', 'replacement'];

    public const REFUND_METHODS = ['cash', 'wallet', 'instapay', 'bank_transfer'];

    public const REJECTED_ACTIONS = ['restock', 'write_off'];

    public function __construct(
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly SupplierAccounts $suppliers,
        private readonly CashDrawer $drawer,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  array<string, int>  $accepted  bin item id => units accepted (missing = none)
     * @param  array<string, list<string>>  $replacementSerials  bin item id => serials of the units sent instead
     */
    public function handle(
        ReturnNote $note,
        array $accepted,
        ?string $resolution,
        ?string $refundMethod,
        ?string $rejectedAction,
        array $replacementSerials = [],
        ?string $settleNote = null,
    ): ReturnNote {
        return DB::transaction(function () use ($note, $accepted, $resolution, $refundMethod, $rejectedAction, $replacementSerials, $settleNote): ReturnNote {
            $note = ReturnNote::query()->lockForUpdate()->findOrFail($note->id);
            if (! $note->status->isOpen()) {
                throw new DomainRuleException('الإذن ده اتسوّى خلاص.', 'note_closed');
            }
            /** @var Collection<int, BinItem> $items */
            $items = $note->items()->lockForUpdate()->get()->keyBy('id');
            if ($unknown = array_diff(array_keys($accepted), $items->keys()->all())) {
                throw new DomainRuleException('فيه سطر مش تبع الإذن ده.', 'bin_item_not_found', 404, ['item_ids' => array_values($unknown)]);
            }

            $acceptedValue = 0;
            $rejectedValue = 0;
            foreach ($items as $item) {
                $qty = $accepted[$item->id] ?? 0;
                if ($qty < 0 || $qty > $item->qty) {
                    throw new DomainRuleException("المقبول من «{$item->variant_name}» لازم يكون بين 0 و {$item->qty}.", 'accepted_qty_invalid', context: ['item_id' => $item->id]);
                }
                $acceptedValue += $qty * $item->unit_cost;
                $rejectedValue += ($item->qty - $qty) * $item->unit_cost;
            }
            $acceptedUnits = array_sum(array_map(fn (BinItem $i) => $accepted[$i->id] ?? 0, $items->all()));
            $rejectedUnits = $items->sum('qty') - $acceptedUnits;

            if ($acceptedUnits > 0 && ! in_array($resolution, self::RESOLUTIONS, true)) {
                throw new DomainRuleException('اختار المورد عوّضك إزاي (رصيد / فلوس / بديل).', 'resolution_required');
            }
            if ($acceptedUnits > 0 && $resolution === 'refund' && ! in_array($refundMethod, self::REFUND_METHODS, true)) {
                throw new DomainRuleException('اختار الفلوس رجعت إزاي.', 'refund_method_required');
            }
            if ($rejectedUnits > 0 && ! in_array($rejectedAction, self::REJECTED_ACTIONS, true)) {
                throw new DomainRuleException('اختار المرفوض يرجع المخزون ولا يتعدم.', 'rejected_action_required');
            }

            $out = new StockReference(MovementType::SupplierReturn, refType: 'supplier_return', refId: $note->id, note: "إذن مرتجع {$note->reference()} — {$note->source_name}");
            $back = new StockReference(MovementType::ReturnsBinBack, refType: 'supplier_return', refId: $note->id, note: "مرفوض من {$note->source_name} ({$note->reference()})");
            $writeOff = new StockReference(MovementType::WriteOff, refType: 'supplier_return', refId: $note->id, note: "مرفوض من {$note->source_name} ({$note->reference()}) واتعدم");
            $replace = new StockReference(MovementType::SupplierReplacement, refType: 'supplier_return', refId: $note->id, note: "بديل من {$note->source_name} ({$note->reference()})");

            foreach ($items as $item) {
                $yes = $accepted[$item->id] ?? 0;
                $no = $item->qty - $yes;

                if ($yes > 0 && $item->serial !== null) {
                    $this->serials->release($item->branch_id, $item->variant_id, [$item->serial], $out);
                }
                if ($yes > 0 && $resolution === 'replacement') {
                    $this->stock->receive($item->branch_id, $item->variant_id, $yes, $item->unit_cost, $replace);
                    if ($item->serial !== null) {
                        $given = $this->serials->normalize($replacementSerials[$item->id] ?? []);
                        if (count($given) !== $yes) {
                            throw new DomainRuleException("اكتب IMEI / سيريال البديل لـ «{$item->variant_name}».", 'serials_required', context: ['item_id' => $item->id]);
                        }
                        $this->serials->receive($item->branch_id, $item->variant_id, $given, $replace);
                    }
                }

                if ($no > 0 && $rejectedAction === 'restock') {
                    $this->stock->restore($item->branch_id, $item->variant_id, $no, $item->unit_cost, $item->lot_id, $back);
                    if ($item->serial !== null) {
                        $this->serials->putBack($item->branch_id, $item->variant_id, [$item->serial], $back);
                    }
                }
                if ($no > 0 && $rejectedAction === 'write_off' && $item->serial !== null) {
                    $this->serials->release($item->branch_id, $item->variant_id, [$item->serial], $writeOff);
                }

                $item->update([
                    'status' => BinItem::SETTLED,
                    'accepted_qty' => $yes,
                    'outcome' => $no === 0 ? 'accepted' : ($yes === 0 ? 'rejected' : 'partial'),
                    'settled_at' => now(),
                ]);
            }

            $label = "إذن مرتجع {$note->reference()}";
            if ($acceptedValue > 0 && $note->source_type === SourceType::Supplier) {
                match ($resolution) {
                    'credit' => $this->suppliers->creditReturn($note->source_id, $acceptedValue, 'supplier_return', $note->id, $label),
                    'refund' => $this->suppliers->refundReturn($note->source_id, $acceptedValue, (string) $refundMethod, 'supplier_return', $note->id, $label),
                    default => null,
                };
            }
            if ($acceptedValue > 0 && $resolution === 'refund' && $refundMethod !== 'bank_transfer') {
                $this->drawer->record($note->branch_id, DrawerEntry::SupplierRefund, (string) $refundMethod, $acceptedValue, 'supplier_return', $note->id, "{$label} — {$note->source_name}");
            }

            $status = $rejectedUnits === 0 ? NoteStatus::Accepted : ($acceptedUnits === 0 ? NoteStatus::Rejected : NoteStatus::PartiallyAccepted);
            $note->update([
                'status' => $status,
                'accepted_value' => $acceptedValue,
                'rejected_value' => $rejectedValue,
                'resolution' => $acceptedUnits > 0 ? $resolution : null,
                'refund_method' => $acceptedUnits > 0 && $resolution === 'refund' ? $refundMethod : null,
                'rejected_action' => $rejectedUnits > 0 ? $rejectedAction : null,
                'settle_note' => $settleNote,
                'settled_by_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
                'sent_at' => $note->sent_at ?? now(),
                'settled_at' => now(),
            ]);

            $this->events->record(new ReturnNoteSettled(
                tenantId: $note->tenant_id,
                noteId: $note->id,
                branchId: $note->branch_id,
                sourceType: $note->source_type->value,
                sourceId: $note->source_id,
                status: $status->value,
                acceptedValue: $acceptedValue,
                rejectedValue: $rejectedValue,
                resolution: $note->resolution,
                rejectedAction: $note->rejected_action,
            ));

            $money = fn (int $v): string => number_format($v / 100, 2).' ج';
            if ($acceptedUnits > 0) {
                $how = match ($resolution) {
                    'credit' => 'رصيد على حسابه',
                    'refund' => 'فلوس',
                    default => 'بديل',
                };
                $this->audit->record('supplier_returns.accepted', "«{$note->source_name}» قبل {$acceptedUnits} قطعة من {$label} بـ {$money($acceptedValue)} ({$how})", $note, ['value' => $acceptedValue, 'resolution' => $resolution]);
            }
            if ($rejectedUnits > 0) {
                $this->audit->record('supplier_returns.rejected', "«{$note->source_name}» رفض {$rejectedUnits} قطعة من {$label} بـ {$money($rejectedValue)}", $note, ['value' => $rejectedValue, 'action' => $rejectedAction]);
                if ($rejectedAction === 'write_off') {
                    $this->audit->record('supplier_returns.written_off', "أعدم {$rejectedUnits} قطعة مرفوضة من {$label} (خسارة {$money($rejectedValue)})", $note, ['value' => $rejectedValue]);
                }
            }

            return $note;
        });
    }
}
