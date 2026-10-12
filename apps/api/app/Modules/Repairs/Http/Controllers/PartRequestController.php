<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Repairs\Actions\PartRequestActions;
use App\Modules\Repairs\Http\Resources\TicketResource;
use App\Modules\Repairs\Models\RepairPartRequest;
use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Tenancy\CurrentBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «طلبات القطع»: technicians ask for parts, the store hands them out. */
final class PartRequestController
{
    public function __construct(
        private readonly CurrentBranch $branch,
        private readonly PartRequestActions $actions,
    ) {}

    /**
     * The branch's requests: ?status=requested (default) | done (handed / refused / cancelled, newest first).
     * Whoever hands parts out sees all of them, a technician their own.
     */
    public function index(Request $request, VariantCatalog $catalog, StockLedger $stock): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        $handler = $user->can('repairs.hand_parts');
        abort_unless($handler || $user->can('repairs.request_parts'), 403);
        $branchId = $this->branch->idOrFail();
        $waiting = $request->query('status', 'requested') !== 'done';

        $rows = RepairPartRequest::query()
            ->with('ticket:id,number,device_name,customer_name,technician_name,status')
            ->where('branch_id', $branchId)
            ->when(! $handler, fn ($q) => $q->where('requested_by', $user->getAuthIdentifier()))
            ->when($waiting, fn ($q) => $q->where('status', RepairPartRequest::REQUESTED)->orderBy('created_at')->orderBy('id'))
            ->when(! $waiting, fn ($q) => $q->where('status', '!=', RepairPartRequest::REQUESTED)->orderByDesc('updated_at'))
            ->limit($waiting ? 200 : 50)
            ->get();

        $ids = $rows->pluck('variant_id')->unique()->values()->all();
        $variants = $catalog->find($ids);
        $quantities = $stock->quantities($branchId, $ids);

        return response()->json([
            'data' => $rows->map(fn (RepairPartRequest $r): array => [
                ...self::row($r),
                'ticket' => [
                    'id' => $r->ticket->id,
                    'reference' => $r->ticket->reference(),
                    'device_name' => $r->ticket->device_name,
                    'customer_name' => $r->ticket->customer_name,
                    'technician_name' => $r->ticket->technician_name,
                ],
                'track_serial' => (bool) ($variants[$r->variant_id] ?? null)?->trackSerial,
                'barcode' => ($variants[$r->variant_id] ?? null)?->barcode,
                'stock' => $quantities[$r->variant_id] ?? 0,
            ])->all(),
            'meta' => ['waiting' => RepairPartRequest::query()->where('branch_id', $branchId)->where('status', RepairPartRequest::REQUESTED)
                ->when(! $handler, fn ($q) => $q->where('requested_by', $user->getAuthIdentifier()))->count()],
        ]);
    }

    public function store(Request $request, RepairTicket $ticket): TicketResource
    {
        $user = $request->user() ?? abort(401);
        abort_unless($user->can('repairs.request_parts'), 403);
        abort_unless($ticket->branch_id === $this->branch->idOrFail(), 404);
        $data = $request->validate([
            'variant_id' => ['required', 'uuid'],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);
        $this->actions->request($ticket, $data['variant_id'], (int) $data['qty'], $data['note'] ?? null, $user);

        return self::ticket($ticket);
    }

    public function hand(Request $request, RepairPartRequest $partRequest): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        abort_unless($user->can('repairs.hand_parts'), 403);
        $this->inBranch($partRequest);
        $data = $request->validate([
            'serials' => ['nullable', 'array', 'max:100'],
            'serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ], [], ['serials.*' => 'السيريال']);
        $done = $this->actions->hand($partRequest, isset($data['serials']) ? array_values(array_map('strval', $data['serials'])) : null, $user);

        return response()->json(['data' => self::row($done)]);
    }

    public function reject(Request $request, RepairPartRequest $partRequest): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        abort_unless($user->can('repairs.hand_parts'), 403);
        $this->inBranch($partRequest);
        $data = $request->validate(['reason' => ['required', 'string', 'max:190']]);

        return response()->json(['data' => self::row($this->actions->reject($partRequest, $data['reason'], $user))]);
    }

    /** The technician takes back a request still waiting. */
    public function destroy(Request $request, RepairPartRequest $partRequest): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        $this->inBranch($partRequest);
        abort_unless($user->can('repairs.hand_parts') || ($user->can('repairs.request_parts') && $partRequest->requested_by === $user->getAuthIdentifier()), 403);

        return response()->json(['data' => self::row($this->actions->cancel($partRequest))]);
    }

    /** @return array<string, mixed> */
    public static function row(RepairPartRequest $r): array
    {
        return [
            'id' => $r->id,
            'ticket_id' => $r->ticket_id,
            'variant_id' => $r->variant_id,
            'name' => $r->name,
            'qty' => $r->qty,
            'note' => $r->note,
            'status' => $r->status,
            'status_label' => RepairPartRequest::LABELS[$r->status] ?? $r->status,
            'requested_by' => $r->requested_by,
            'requested_by_name' => $r->requested_by_name,
            'handled_by_name' => $r->handled_by_name,
            'handled_at' => $r->handled_at?->toIso8601String(),
            'reason' => $r->reason,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    private static function ticket(RepairTicket $ticket): TicketResource
    {
        return new TicketResource($ticket->refresh()->load(['parts', 'payments', 'events', 'partRequests']));
    }

    private function inBranch(RepairPartRequest $request): void
    {
        abort_unless($request->branch_id === $this->branch->idOrFail(), 404);
    }
}
