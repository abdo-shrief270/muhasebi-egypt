<?php

declare(strict_types=1);

namespace App\Modules\MultiBranch\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\MultiBranch\Actions\TransferActions;
use App\Modules\MultiBranch\Enums\TransferStatus;
use App\Modules\MultiBranch\Models\StockTransfer;
use App\Modules\MultiBranch\Models\StockTransferItem;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «التحويلات» between the shop's branches (transfers.manage), limited to the user's branches. */
final class TransferController
{
    public function __construct(
        private readonly BranchDirectory $branches,
        private readonly TransferActions $actions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(TransferStatus::class)],
            'box' => ['nullable', 'in:incoming,outgoing,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $mine = array_keys($this->branches->accessibleBranches($this->user($request)));
        $box = $data['box'] ?? 'all';
        $page = StockTransfer::query()->with('items')
            ->when($box === 'incoming', fn ($q) => $q->whereIn('to_branch_id', $mine))
            ->when($box === 'outgoing', fn ($q) => $q->whereIn('from_branch_id', $mine))
            ->when($box === 'all', fn ($q) => $q->where(fn ($w) => $w->whereIn('from_branch_id', $mine)->orWhereIn('to_branch_id', $mine)))
            ->when(isset($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (StockTransfer $t) => $this->present($request, $t))->all(),
            'meta' => [
                'total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                // Waiting for this user: shipped to their branches, or requested from them.
                'to_receive' => StockTransfer::query()->where('status', TransferStatus::Shipped)->whereIn('to_branch_id', $mine)->count(),
                'to_ship' => StockTransfer::query()->where('status', TransferStatus::Requested)->whereIn('from_branch_id', $mine)->count(),
                // Every branch (a transfer may go to one the user doesn't work in), theirs marked.
                'branches' => array_map(fn (string $id, string $name) => ['id' => $id, 'name' => $name, 'mine' => in_array($id, $mine, true)], array_keys($all = $this->branches->all()), $all),
            ],
        ]);
    }

    /** Variants to add to a transfer, with what the sending branch has of each. */
    public function variants(Request $request, VariantCatalog $catalog, StockLedger $stock, SerialRegistry $serials): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'branch_id' => ['required', 'uuid']]);
        $q = trim((string) ($data['q'] ?? ''));
        $items = $catalog->search($q === '' ? null : $q, null, null, 1, 20)['items'];
        usort($items, fn (VariantSummary $a, VariantSummary $b): int => (int) ($b->barcode === $q) <=> (int) ($a->barcode === $q));
        $ids = array_map(fn (VariantSummary $v) => $v->id, $items);
        $qty = $stock->quantities($data['branch_id'], $ids);
        $inStock = $serials->inStock($data['branch_id'], $ids);

        return response()->json(['data' => array_map(fn (VariantSummary $v): array => [
            'id' => $v->id,
            'display_name' => $v->displayName(),
            'barcode' => $v->barcode,
            'category' => ['id' => $v->categoryId, 'name' => $v->categoryName],
            'track_serial' => $v->trackSerial,
            'qty' => $qty[$v->id] ?? 0,
            'serials' => $inStock[$v->id] ?? [],
            'exact_barcode' => $q !== '' && $v->barcode === $q,
        ], $items)]);
    }

    public function store(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $tenantId = $tenant->idOrFail();
        $data = $request->validate([
            'from_branch_id' => ['required', 'uuid', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'to_branch_id' => ['required', 'uuid', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
            'ship_now' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.serials' => ['nullable', 'array', 'max:1000'],
            'items.*.serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ], [], ['from_branch_id' => 'من فرع', 'to_branch_id' => 'لفرع', 'items' => 'الأصناف', 'items.*.qty' => 'الكمية']);

        $transfer = $this->actions->create(
            $this->user($request), $tenantId, $data['from_branch_id'], $data['to_branch_id'],
            array_map(fn (array $i) => ['variant_id' => (string) $i['variant_id'], 'qty' => (int) $i['qty'], 'serials' => $i['serials'] ?? null], $data['items']),
            $data['notes'] ?? null, (bool) ($data['ship_now'] ?? false),
        );

        return response()->json(['data' => $this->present($request, $transfer, true)], 201);
    }

    public function show(Request $request, StockTransfer $transfer): JsonResponse
    {
        $this->ensureVisible($request, $transfer);

        return response()->json(['data' => $this->present($request, $transfer->load('items'), true)]);
    }

    public function ship(Request $request, StockTransfer $transfer): JsonResponse
    {
        $this->ensureVisible($request, $transfer);

        return response()->json(['data' => $this->present($request, $this->actions->ship($this->user($request), $transfer, $this->lines($request)), true)]);
    }

    public function receive(Request $request, StockTransfer $transfer): JsonResponse
    {
        $this->ensureVisible($request, $transfer);

        return response()->json(['data' => $this->present($request, $this->actions->receive($this->user($request), $transfer, $this->lines($request)), true)]);
    }

    public function cancel(Request $request, StockTransfer $transfer): JsonResponse
    {
        $this->ensureVisible($request, $transfer);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'السبب']);

        return response()->json(['data' => $this->present($request, $this->actions->cancel($this->user($request), $transfer, $data['reason']), true)]);
    }

    /** @return list<array{item_id: string, qty: int, serials: list<string>|null}> */
    private function lines(Request $request): array
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'max:200'],
            'lines.*.item_id' => ['required', 'uuid', 'distinct'],
            'lines.*.qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'lines.*.serials' => ['nullable', 'array', 'max:1000'],
            'lines.*.serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ]);

        return array_values(array_map(fn (array $l) => ['item_id' => (string) $l['item_id'], 'qty' => (int) $l['qty'], 'serials' => $l['serials'] ?? null], $data['lines']));
    }

    private function ensureVisible(Request $request, StockTransfer $transfer): void
    {
        $mine = $this->branches->accessibleBranches($this->user($request));
        abort_unless(isset($mine[$transfer->from_branch_id]) || isset($mine[$transfer->to_branch_id]), 404);
    }

    private function user(Request $request): Authenticatable
    {
        return $request->user() ?? abort(401);
    }

    /** @return array<string, mixed> */
    private function present(Request $request, StockTransfer $t, bool $full = false): array
    {
        $names = $this->branches->all();
        $mine = $this->branches->accessibleBranches($this->user($request));
        $costs = (bool) $request->user()?->can('products.view_cost');
        $data = [
            'id' => $t->id,
            'number' => $t->number,
            'reference' => $t->reference(),
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'from' => ['id' => $t->from_branch_id, 'name' => $names[$t->from_branch_id] ?? '—'],
            'to' => ['id' => $t->to_branch_id, 'name' => $names[$t->to_branch_id] ?? '—'],
            'units_requested' => (int) $t->items->sum('qty_requested'),
            'units_shipped' => (int) $t->items->sum('qty_shipped'),
            'units_received' => (int) $t->items->sum('qty_received'),
            'value' => $costs ? (int) $t->items->sum(fn (StockTransferItem $i) => $i->qty_shipped * $i->unit_cost) : null,
            'can_ship' => $t->status === TransferStatus::Requested && isset($mine[$t->from_branch_id]),
            'can_receive' => $t->status === TransferStatus::Shipped && isset($mine[$t->to_branch_id]),
            'notes' => $t->notes,
            'requested_by_name' => $t->requested_by_name,
            'created_at' => $t->created_at->toIso8601String(),
            'shipped_by_name' => $t->shipped_by_name,
            'shipped_at' => $t->shipped_at?->toIso8601String(),
            'received_by_name' => $t->received_by_name,
            'received_at' => $t->received_at?->toIso8601String(),
            'cancel_reason' => $t->cancel_reason,
        ];
        if ($full) {
            $data['items'] = $t->items->map(fn (StockTransferItem $i) => [
                'id' => $i->id,
                'variant_id' => $i->variant_id,
                'name' => $i->name,
                'track_serial' => $i->track_serial,
                'qty_requested' => $i->qty_requested,
                'qty_shipped' => $i->qty_shipped,
                'qty_received' => $i->qty_received,
                'unit_cost' => $costs ? $i->unit_cost : null,
                'serials' => $i->serials ?? [],
                'received_serials' => $i->received_serials ?? [],
            ])->values()->all();
        }

        return $data;
    }
}
