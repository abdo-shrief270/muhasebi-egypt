<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Models\RepairPartRequest;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * «طلب قطعة»: the technician asks for a part for a ticket; whoever hands parts out (store, counter,
 * manager) gives it — it then goes on the ticket like any part (stock out, the technician price) —
 * or refuses it with a reason; the technician may take back a request still waiting.
 */
final class PartRequestActions
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly AddPartAction $addPart,
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
        private readonly Notifications $notifications,
    ) {}

    public function request(RepairTicket $ticket, string $variantId, int $qty, ?string $note, Authenticatable $user): RepairPartRequest
    {
        if (! $ticket->status->isOpen()) {
            throw new DomainRuleException('الجهاز اتسلّم خلاص؛ مينفعش تطلب قطع.', 'ticket_closed');
        }
        $variant = $this->catalog->find([$variantId])[$variantId] ?? throw new DomainRuleException('الصنف مش موجود.', 'variant_not_found', 404);
        $name = (string) $user->getAttribute('name');

        return DB::transaction(function () use ($ticket, $variant, $qty, $note, $user, $name): RepairPartRequest {
            $request = RepairPartRequest::create([
                'tenant_id' => $ticket->tenant_id,
                'branch_id' => $ticket->branch_id,
                'ticket_id' => $ticket->id,
                'variant_id' => $variant->id,
                'name' => $variant->displayName(),
                'qty' => $qty,
                'note' => $note,
                'status' => RepairPartRequest::REQUESTED,
                'requested_by' => $user->getAuthIdentifier(),
                'requested_by_name' => $name,
            ]);
            $this->timeline->add($ticket, EventType::PartRequest, "طلب {$qty} × {$request->name}");
            $this->notifications->notify(
                'repair_part.requested',
                "طلب قطعة للتذكرة {$ticket->reference()}",
                "{$name} محتاج {$qty} × {$request->name}".($note ? " — {$note}" : ''),
                'i-lucide-package-search',
                '/repairs/parts',
                'repairs.hand_parts',
            );

            return $request;
        });
    }

    /**
     * @param  list<string>|null  $serials
     */
    public function hand(RepairPartRequest $request, ?array $serials, Authenticatable $user): RepairPartRequest
    {
        return DB::transaction(function () use ($request, $serials, $user): RepairPartRequest {
            $request = $this->waiting($request);
            $ticket = RepairTicket::query()->findOrFail($request->ticket_id);
            $part = $this->addPart->handle($ticket, $request->variant_id, $request->qty, null, $serials, $request->requested_by_name);
            $request->update([
                'status' => RepairPartRequest::HANDED,
                'part_id' => $part->id,
                'handled_by' => $user->getAuthIdentifier(),
                'handled_by_name' => $user->getAttribute('name'),
                'handled_at' => now(),
            ]);
            $this->audit->record(
                'repairs.part_handed',
                "صرف {$request->qty} × {$request->name} لـ {$request->requested_by_name} على التذكرة {$ticket->reference()}",
                $ticket,
            );

            return $request;
        });
    }

    public function reject(RepairPartRequest $request, string $reason, Authenticatable $user): RepairPartRequest
    {
        return DB::transaction(function () use ($request, $reason, $user): RepairPartRequest {
            $request = $this->waiting($request);
            $request->update([
                'status' => RepairPartRequest::REJECTED,
                'reason' => $reason,
                'handled_by' => $user->getAuthIdentifier(),
                'handled_by_name' => $user->getAttribute('name'),
                'handled_at' => now(),
            ]);
            $this->timeline->add($request->ticket, EventType::PartRequest, "اترفض طلب {$request->qty} × {$request->name}: {$reason}");

            return $request;
        });
    }

    public function cancel(RepairPartRequest $request): RepairPartRequest
    {
        return DB::transaction(function () use ($request): RepairPartRequest {
            $request = $this->waiting($request);
            $request->update(['status' => RepairPartRequest::CANCELLED]);
            $this->timeline->add($request->ticket, EventType::PartRequest, "اتلغى طلب {$request->qty} × {$request->name}");

            return $request;
        });
    }

    /** The request, locked, still waiting to be handed out. */
    private function waiting(RepairPartRequest $request): RepairPartRequest
    {
        $request = RepairPartRequest::query()->lockForUpdate()->findOrFail($request->id);
        if ($request->status !== RepairPartRequest::REQUESTED) {
            throw new DomainRuleException('الطلب ده اتقفل خلاص ('.RepairPartRequest::LABELS[$request->status].').', 'part_request_closed');
        }

        return $request;
    }
}
