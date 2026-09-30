<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Actions;

use App\Modules\SupplierReturns\Enums\NoteStatus;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Modules\SupplierReturns\Models\ReturnNote;
use App\Modules\SupplierReturns\Support\SourceDetector;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Selected bin lines become return notes: one per source, numbered per shop. */
final class CreateNotesAction
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly SourceDetector $detector,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  list<string>  $itemIds
     * @return list<ReturnNote>
     */
    public function handle(string $tenantId, string $branchId, array $itemIds, ?string $notes = null): array
    {
        return DB::transaction(function () use ($tenantId, $branchId, $itemIds, $notes): array {
            /** @var Collection<int, BinItem> $items */
            $items = BinItem::query()->whereIn('id', $itemIds)->where('branch_id', $branchId)->orderBy('seq')->lockForUpdate()->get();
            if ($items->count() !== count(array_unique($itemIds))) {
                throw new DomainRuleException('فيه قطع مش موجودة في سلة الفرع ده.', 'bin_item_not_found', 404);
            }
            if ($items->contains(fn (BinItem $i) => $i->status !== BinItem::IN_BIN)) {
                throw new DomainRuleException('فيه قطع اتحطت على إذن مرتجع قبل كده.', 'not_in_bin');
            }
            if ($items->contains(fn (BinItem $i) => $i->source_type === null)) {
                throw new DomainRuleException('فيه قطع لسه مصدرها مش معروف — اختار المصدر الأول.', 'source_required');
            }

            $user = $this->auth->guard('sanctum')->user();
            $created = [];
            foreach ($items->groupBy(fn (BinItem $i) => $i->source_type?->value.':'.$i->source_id) as $group) {
                /** @var BinItem $first */
                $first = $group->first();
                $contact = $this->detector->contact($first->source_type, (string) $first->source_id);
                $note = ReturnNote::create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'number' => $this->numbers->next($tenantId, 'supplier_return'),
                    'source_type' => $first->source_type,
                    'source_id' => $first->source_id,
                    'source_name' => $contact->name ?? (string) $first->source_name,
                    'source_phone' => $contact?->phone,
                    'status' => NoteStatus::Pending,
                    'units' => $group->sum('qty'),
                    'total_cost' => $group->sum(fn (BinItem $i) => $i->value()),
                    'notes' => $notes,
                    'created_by' => $user?->getAuthIdentifier(),
                    'created_by_name' => $user?->getAttribute('name'),
                ]);
                BinItem::query()->whereIn('id', $group->pluck('id'))->update(['supplier_return_id' => $note->id, 'status' => BinItem::ON_NOTE]);
                $this->audit->record('supplier_returns.note_created', "عمل إذن مرتجع {$note->reference()} لـ «{$note->source_name}» ({$note->units} قطعة)", $note);
                $created[] = $note;
            }

            return $created;
        });
    }
}
