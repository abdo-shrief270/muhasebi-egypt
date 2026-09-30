<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Http\Controllers;

use App\Modules\SupplierReturns\Actions\AddToBinAction;
use App\Modules\SupplierReturns\Actions\BinItemActions;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Modules\SupplierReturns\Enums\NoteStatus;
use App\Modules\SupplierReturns\Enums\SourceType;
use App\Modules\SupplierReturns\Http\Requests\AddToBinRequest;
use App\Modules\SupplierReturns\Http\Resources\BinItemResource;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Modules\SupplierReturns\Models\ReturnNote;
use App\Modules\SupplierReturns\Support\SourceDetector;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BinController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    /** The sorting screen: the branch's bin grouped by source (unknown source last). */
    public function index(Request $request): JsonResponse
    {
        $cost = (bool) $request->user()?->can('products.view_cost');
        $items = BinItem::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->where('status', BinItem::IN_BIN)
            ->orderBy('seq')
            ->get();

        $groups = $items
            ->groupBy(fn (BinItem $i): string => $i->source_type === null ? 'unknown' : $i->source_type->value.':'.$i->source_id)
            ->map(function ($group, string $key) use ($request, $cost): array {
                /** @var BinItem $first */
                $first = $group->first();

                return [
                    'key' => $key,
                    'source' => $first->source_type === null ? null : [
                        'type' => $first->source_type->value,
                        'type_label' => $first->source_type->label(),
                        'id' => $first->source_id,
                        'name' => $first->source_name,
                    ],
                    'lines' => $group->count(),
                    'units' => (int) $group->sum('qty'),
                    'value' => $cost ? (int) $group->sum(fn (BinItem $i) => $i->value()) : null,
                    'items' => BinItemResource::collection($group->values())->toArray($request),
                ];
            })
            ->sortBy(fn (array $g) => [$g['source'] === null ? 1 : 0, -$g['units']])
            ->values();

        return response()->json(['data' => [
            'groups' => $groups,
            'units' => (int) $items->sum('qty'),
            'value' => $cost ? (int) $items->sum(fn (BinItem $i) => $i->value()) : null,
            'open_notes' => ReturnNote::query()->where('branch_id', $this->branch->idOrFail())->whereIn('status', [NoteStatus::Pending, NoteStatus::Sent])->count(),
            'reasons' => ReturnReason::options(),
        ]]);
    }

    public function store(AddToBinRequest $request, AddToBinAction $action): JsonResponse
    {
        $rows = $action->fromStock(
            tenantId: $this->tenant->idOrFail(),
            branchId: $this->branch->idOrFail(),
            variantId: (string) $request->validated('variant_id'),
            qty: (int) $request->validated('qty'),
            serials: $request->validated('serials'),
            reason: ReturnReason::from((string) $request->validated('reason')),
            note: $request->validated('note'),
            source: $request->source(),
        );

        return response()->json(['data' => BinItemResource::collection($rows)->toArray($request)], 201);
    }

    public function update(Request $request, string $item, BinItemActions $actions): BinItemResource
    {
        abort_unless((bool) $request->user()?->can('supplier_returns.manage'), 403);
        $data = $request->validate([
            'reason' => ['nullable', Rule::enum(ReturnReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'array'],
            'source.type' => ['required_with:source', Rule::enum(SourceType::class)],
            'source.id' => ['required_with:source', 'uuid'],
        ]);

        return new BinItemResource($actions->update(
            $this->find($item),
            isset($data['reason']) ? ReturnReason::from($data['reason']) : null,
            array_key_exists('note', $data) ? (string) $data['note'] : null,
            isset($data['source']) ? ['type' => (string) $data['source']['type'], 'id' => (string) $data['source']['id']] : null,
        ));
    }

    public function restock(Request $request, string $item, BinItemActions $actions): BinItemResource
    {
        abort_unless((bool) $request->user()?->can('supplier_returns.manage'), 403);

        return new BinItemResource($actions->restock($this->find($item)));
    }

    public function writeOff(Request $request, string $item, BinItemActions $actions): BinItemResource
    {
        abort_unless((bool) $request->user()?->can('supplier_returns.manage'), 403);

        return new BinItemResource($actions->writeOff($this->find($item)));
    }

    /** Where a unit of this variant could have come from: recent purchases first, then everyone. */
    public function sources(Request $request, SourceDetector $detector): JsonResponse
    {
        $data = $request->validate(['variant_id' => ['nullable', 'uuid']]);
        $candidates = $detector->candidates($data['variant_id'] ?? null);
        if (! $request->user()?->can('products.view_cost')) {
            $candidates['suggested'] = array_map(fn (array $s): array => [...$s, 'unit_cost' => null], $candidates['suggested']);
        }

        return response()->json(['data' => $candidates]);
    }

    private function find(string $id): BinItem
    {
        return BinItem::query()->where('branch_id', $this->branch->idOrFail())->findOrFail($id);
    }
}
