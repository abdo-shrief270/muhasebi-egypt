<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Messaging\Contracts\MessageHistory;
use App\Modules\Repairs\Actions\AddPartAction;
use App\Modules\Repairs\Actions\ChangeStatusAction;
use App\Modules\Repairs\Actions\DeliverTicketAction;
use App\Modules\Repairs\Actions\OutsourceTicketAction;
use App\Modules\Repairs\Actions\ReceiveDeviceAction;
use App\Modules\Repairs\Actions\RemovePartAction;
use App\Modules\Repairs\Actions\UpdateTicketAction;
use App\Modules\Repairs\Actions\WarrantyReturnAction;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Http\Requests\DeliverTicketRequest;
use App\Modules\Repairs\Http\Requests\ReceiveDeviceRequest;
use App\Modules\Repairs\Http\Requests\UpdateTicketRequest;
use App\Modules\Repairs\Http\Resources\FaultCategoryResource;
use App\Modules\Repairs\Http\Resources\TicketResource;
use App\Modules\Repairs\Models\FaultCategory;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Support\IntakeOptions;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class TicketController
{
    /** A device ready this many days and not picked up counts as left behind. */
    public const ABANDONED_AFTER_DAYS = 14;

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
        private readonly StaffDirectory $staff,
        private readonly MessageHistory $messages,
        private readonly FeatureAccess $features,
    ) {}

    /**
     * Tickets of this branch. ?status=open (default) | ready | delivered | all | a status,
     * ?q= ticket number, customer, phone, IMEI or device, ?overdue=1, ?abandoned=1, ?mine=1.
     */
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'open');
        $tickets = $this->filtered($request)
            ->when($status === 'open', fn ($q) => $q->where('status', '!=', TicketStatus::Delivered->value))
            ->when($status === 'delivered', fn ($q) => $q->where('status', TicketStatus::Delivered->value))
            ->when(TicketStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->when($status === 'delivered', fn ($q) => $q->orderByDesc('delivered_at'), fn ($q) => $q->orderByRaw('expected_at asc nulls last')->orderByDesc('received_at'))
            ->paginate(30);
        $this->markNotified($tickets->getCollection());

        return response()->json([
            'data' => TicketResource::collection($tickets->getCollection()),
            'meta' => ['current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage(), 'total' => $tickets->total()],
        ]);
    }

    /** Counts for the list tabs and the home page. */
    public function summary(Request $request): JsonResponse
    {
        $base = fn () => RepairTicket::query()->where('branch_id', $this->branch->idOrFail());

        return response()->json(['data' => [
            'open' => $base()->where('status', '!=', TicketStatus::Delivered->value)->count(),
            'ready' => $base()->where('status', TicketStatus::Ready->value)->count(),
            'unnotified' => $this->features->enabled('repairs.status_whatsapp') ? $this->unnotified($base()) : 0,
            'overdue' => $this->overdue($base())->count(),
            'abandoned' => $this->abandoned($base())->count(),
            'mine' => $base()->where('status', '!=', TicketStatus::Delivered->value)->where('technician_id', $request->user()?->getAuthIdentifier())->count(),
        ]]);
    }

    /** What the intake and ticket screens pick from. */
    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            ...IntakeOptions::toArray(),
            'statuses' => array_map(fn (TicketStatus $s) => ['value' => $s->value, 'label' => $s->label()], TicketStatus::cases()),
            'technicians' => array_map(fn (string $id, string $name) => ['id' => $id, 'name' => $name], array_keys($t = $this->staff->withPermission('repairs.update_status')), $t),
            'faults' => FaultCategoryResource::collection(FaultCategory::query()->with('types')->orderBy('sort')->get()),
        ]]);
    }

    public function show(RepairTicket $ticket): TicketResource
    {
        $this->inBranch($ticket);

        $this->markNotified(collect([$ticket]));

        return new TicketResource($ticket->load(['parts', 'payments', 'events']));
    }

    /**
     * Ready tickets get when the customer was last told on WhatsApp (after it became ready).
     *
     * @param  iterable<RepairTicket>  $tickets
     */
    private function markNotified(iterable $tickets): void
    {
        $ready = collect($tickets)->filter(fn (RepairTicket $t) => $t->status === TicketStatus::Ready);
        $sent = $this->messages->lastSent('repair_ticket', $ready->pluck('id')->values()->all(), 'repair_ready');
        foreach ($ready as $ticket) {
            $at = $sent[$ticket->id] ?? null;
            $ticket->setAttribute('ready_notified_at', $at !== null && ($ticket->ready_at === null || Carbon::parse($at)->gte($ticket->ready_at)) ? $at : null);
        }
    }

    /** Ready devices whose customer wasn't told yet. */
    private function unnotified(Builder $query): int
    {
        $ready = $query->where('status', TicketStatus::Ready->value)->get(['id', 'status', 'ready_at']);
        $this->markNotified($ready);

        return $ready->filter(fn (RepairTicket $t) => ($t->getAttributes()['ready_notified_at'] ?? null) === null)->count();
    }

    public function store(ReceiveDeviceRequest $request, ReceiveDeviceAction $action): JsonResponse
    {
        $data = $request->validated();
        if ($request->deposits() !== []) {
            $this->features->ensure('repairs.deposits');
        }
        $data['technician_name'] = $this->technicianName($data['technician_id'] ?? null);
        $ticket = $action->handle($this->tenant->idOrFail(), $this->branch->idOrFail(), $data, $request->deposits());

        return $this->show($ticket)->response()->setStatusCode(201);
    }

    public function update(UpdateTicketRequest $request, RepairTicket $ticket, UpdateTicketAction $action): TicketResource
    {
        $this->inBranch($ticket);
        $data = $request->validated();
        if (array_key_exists('technician_id', $data)) {
            $data['technician_name'] = $this->technicianName($data['technician_id']);
        }

        return $this->show($action->handle($ticket, $data));
    }

    /** Send the device to a partner shop for repair. */
    public function outsource(Request $request, RepairTicket $ticket, OutsourceTicketAction $action): TicketResource
    {
        $this->inBranch($ticket);
        $user = $request->user();
        abort_unless($user !== null && $user->can('repairs.update_status') && $user->can('shop_orders.place'), 403);
        $data = $request->validate([
            'partner_tenant_id' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:190'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        return $this->show($action->handle($ticket, $data['partner_tenant_id'], (string) $user->getAuthIdentifier(), $data['note'] ?? null, $data['needed_by'] ?? null));
    }

    public function status(Request $request, RepairTicket $ticket, ChangeStatusAction $action): TicketResource
    {
        $this->inBranch($ticket);
        abort_unless((bool) $request->user()?->can('repairs.update_status'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)->except([TicketStatus::Delivered])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->show($action->handle($ticket, TicketStatus::from($data['status']), $data['note'] ?? null));
    }

    public function addPart(Request $request, RepairTicket $ticket, AddPartAction $action): TicketResource
    {
        $this->inBranch($ticket);
        abort_unless((bool) $request->user()?->can('repairs.update_status'), 403);
        $data = $request->validate([
            'variant_id' => ['required', 'uuid'],
            'qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit_price' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'serials' => ['nullable', 'array', 'max:1000'],
            'serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ], [], ['serials.*' => 'السيريال']);
        $action->handle(
            $ticket,
            $data['variant_id'],
            (int) $data['qty'],
            isset($data['unit_price']) ? (int) $data['unit_price'] : null,
            isset($data['serials']) ? array_values(array_map('strval', $data['serials'])) : null,
        );

        return $this->show($ticket->refresh());
    }

    public function removePart(Request $request, RepairTicket $ticket, int $part, RemovePartAction $action): TicketResource
    {
        $this->inBranch($ticket);
        abort_unless((bool) $request->user()?->can('repairs.update_status'), 403);
        $model = RepairTicketPart::query()->where('ticket_id', $ticket->id)->findOrFail($part);
        // ?defective=1: the part is bad — it stays out of stock (and goes to the supplier returns bin).
        $data = $request->validate(['defective' => ['nullable', 'boolean'], 'reason' => ['nullable', Rule::enum(ReturnReason::class)]]);

        return $this->show($action->handle($ticket, $model, (bool) ($data['defective'] ?? false), $data['reason'] ?? null));
    }

    public function deliver(DeliverTicketRequest $request, RepairTicket $ticket, DeliverTicketAction $action): TicketResource
    {
        $this->inBranch($ticket);
        if ((int) ($request->validated('warranty_days') ?? 0) > 0) {
            $this->features->ensure('repairs.warranty');
        }

        return $this->show($action->handle(
            $ticket,
            $request->payments(),
            (int) ($request->validated('warranty_days') ?? 0),
            $request->validated('note'),
            (bool) $request->user()?->can('customers.credit'),
        ));
    }

    public function warranty(Request $request, RepairTicket $ticket, WarrantyReturnAction $action): JsonResponse
    {
        $this->inBranch($ticket);
        abort_unless((bool) $request->user()?->can('repairs.create'), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        return $this->show($action->handle($ticket, $this->branch->idOrFail(), $data['note'] ?? null))->response()->setStatusCode(201);
    }

    /**
     * @return Builder<RepairTicket>
     */
    private function filtered(Request $request): Builder
    {
        return RepairTicket::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->query('q'));
                $number = (int) preg_replace('/\D/', '', $term);
                $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');
                $query->where(fn (Builder $w) => $w
                    ->where('customer_name', 'ilike', "%{$term}%")
                    ->orWhere('device_name', 'ilike', "%{$term}%")
                    ->when(strlen($digits) >= 4, fn ($x) => $x->orWhere('customer_phone', 'like', "%{$digits}%")->orWhere('imei', 'like', "%{$digits}%"))
                    ->when($number > 0, fn ($x) => $x->orWhere('number', $number)));
            })
            ->when($request->boolean('overdue'), fn ($q) => $this->overdue($q))
            ->when($request->boolean('abandoned'), fn ($q) => $this->abandoned($q))
            ->when($request->boolean('mine'), fn ($q) => $q->where('technician_id', $request->user()?->getAuthIdentifier()));
    }

    /**
     * @param  Builder<RepairTicket>  $query
     * @return Builder<RepairTicket>
     */
    private function overdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TicketStatus::Delivered->value, TicketStatus::Ready->value, TicketStatus::Rejected->value])
            ->where('expected_at', '<', now());
    }

    /**
     * @param  Builder<RepairTicket>  $query
     * @return Builder<RepairTicket>
     */
    private function abandoned(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Ready->value, TicketStatus::Rejected->value])
            ->where('updated_at', '<', now()->subDays(self::ABANDONED_AFTER_DAYS));
    }

    /** A ticket is worked on in its own branch (switch branch to reach another's). */
    private function inBranch(RepairTicket $ticket): void
    {
        abort_unless($ticket->branch_id === $this->branch->idOrFail(), 404);
    }

    private function technicianName(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->staff->withPermission('repairs.update_status')[$id]
            ?? throw new DomainRuleException('الفني ده مش موجود أو معندوش صلاحية الصيانة.', 'technician_not_found');
    }
}
