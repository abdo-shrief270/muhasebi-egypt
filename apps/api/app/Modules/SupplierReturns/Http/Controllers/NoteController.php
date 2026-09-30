<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Http\Controllers;

use App\Modules\SupplierReturns\Actions\CreateNotesAction;
use App\Modules\SupplierReturns\Actions\NoteTransitions;
use App\Modules\SupplierReturns\Actions\SettleNoteAction;
use App\Modules\SupplierReturns\Enums\NoteStatus;
use App\Modules\SupplierReturns\Http\Requests\SettleNoteRequest;
use App\Modules\SupplierReturns\Http\Resources\ReturnNoteResource;
use App\Modules\SupplierReturns\Models\ReturnNote;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NoteController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    /** ?status=open | settled | all (default all) */
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'all');
        $notes = ReturnNote::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->when($status === 'open', fn ($q) => $q->whereIn('status', [NoteStatus::Pending, NoteStatus::Sent]))
            ->when($status === 'settled', fn ($q) => $q->whereNotIn('status', [NoteStatus::Pending, NoteStatus::Sent]))
            ->orderByDesc('number')
            ->paginate(30);

        return response()->json([
            'data' => ReturnNoteResource::collection($notes->getCollection())->toArray($request),
            'meta' => ['current_page' => $notes->currentPage(), 'last_page' => $notes->lastPage(), 'per_page' => $notes->perPage(), 'total' => $notes->total()],
        ]);
    }

    public function show(string $note): ReturnNoteResource
    {
        return new ReturnNoteResource($this->find($note)->load('items'));
    }

    /** Selected bin lines → one note per source. */
    public function store(Request $request, CreateNotesAction $action): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('supplier_returns.manage'), 403);
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['uuid', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $notes = $action->handle($this->tenant->idOrFail(), $this->branch->idOrFail(), array_values($data['item_ids']), $data['notes'] ?? null);

        return response()->json(['data' => ReturnNoteResource::collection($notes)->toArray($request)], 201);
    }

    public function send(Request $request, string $note, NoteTransitions $transitions): ReturnNoteResource
    {
        abort_unless((bool) $request->user()?->can('supplier_returns.manage'), 403);

        return new ReturnNoteResource($transitions->send($this->find($note))->load('items'));
    }

    public function cancel(Request $request, string $note, NoteTransitions $transitions): ReturnNoteResource
    {
        abort_unless((bool) $request->user()?->can('supplier_returns.manage'), 403);

        return new ReturnNoteResource($transitions->cancel($this->find($note))->load('items'));
    }

    public function settle(SettleNoteRequest $request, string $note, SettleNoteAction $action): ReturnNoteResource
    {
        return new ReturnNoteResource($action->handle(
            $this->find($note),
            $request->accepted(),
            $request->validated('resolution'),
            $request->validated('refund_method'),
            $request->validated('rejected_action'),
            $request->replacementSerials(),
            $request->validated('note'),
        )->load('items'));
    }

    private function find(string $id): ReturnNote
    {
        return ReturnNote::query()->where('branch_id', $this->branch->idOrFail())->findOrFail($id);
    }
}
