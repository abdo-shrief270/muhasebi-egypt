<?php

declare(strict_types=1);

namespace App\Modules\Imports\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Imports\Actions\ShipmentActions;
use App\Modules\Imports\Enums\ShipmentStatus;
use App\Modules\Imports\Models\ImportAttachment;
use App\Modules\Imports\Models\ImportContact;
use App\Modules\Imports\Models\ImportShipment;
use App\Modules\Imports\Models\ImportShipmentCost;
use App\Modules\Imports\Support\ImportFiles;
use App\Modules\Imports\Support\ShipmentView;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Time\ShopDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** «الشحنات»: what's ordered, on its way, at customs, arrived — and received at landed cost. */
final class ShipmentController
{
    public function __construct(
        private readonly ShipmentActions $actions,
        private readonly ShipmentView $view,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(ShipmentStatus::cases(), 'value'), 'open', 'late'])],
            'contact_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = $data['status'] ?? null;
        $open = array_map(fn (ShipmentStatus $s) => $s->value, ShipmentStatus::onTheWay());
        $page = ImportShipment::query()->with('contact')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', $open))
            ->when($status === 'late', fn ($q) => $q->whereIn('status', $open)->where('expected_on', '<', ShopDay::today()->toDateString()))
            ->when($status !== null && ! in_array($status, ['open', 'late'], true), fn ($q) => $q->where('status', $status))
            ->when(isset($data['contact_id']), fn ($q) => $q->where('contact_id', $data['contact_id']))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (ImportShipment $s) => $this->view->row($s))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** What the importer owes, what's on the way, what's late. */
    public function summary(): JsonResponse
    {
        $open = array_map(fn (ShipmentStatus $s) => $s->value, ShipmentStatus::onTheWay());

        return response()->json(['data' => [
            'owed' => (int) ImportContact::query()->where('balance', '>', 0)->sum('balance'),
            'credit' => (int) -ImportContact::query()->where('balance', '<', 0)->sum('balance'),
            'on_the_way' => ImportShipment::query()->whereIn('status', $open)->count(),
            'on_the_way_value' => (int) ImportShipment::query()->whereIn('status', $open)->sum('goods_total'),
            'late' => ImportShipment::query()->whereIn('status', $open)->where('expected_on', '<', ShopDay::today()->toDateString())->count(),
            'by_status' => ImportShipment::query()->whereIn('status', $open)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'top_owed' => ImportContact::query()->where('balance', '>', 0)->orderByDesc('balance')->limit(5)->get()->map(fn (ImportContact $c) => $c->toApi())->all(),
        ]]);
    }

    /** Variants to put on a shipment. */
    public function variants(Request $request, VariantCatalog $catalog): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $items = $catalog->search($q === '' ? null : $q, null, null, 1, 20)['items'];
        usort($items, fn (VariantSummary $a, VariantSummary $b): int => (int) ($b->barcode === $q) <=> (int) ($a->barcode === $q));

        return response()->json(['data' => array_map(fn (VariantSummary $v): array => [
            'id' => $v->id, 'display_name' => $v->displayName(), 'barcode' => $v->barcode,
            'category' => ['id' => $v->categoryId, 'name' => $v->categoryName], 'track_serial' => $v->trackSerial,
            'exact_barcode' => $q !== '' && $v->barcode === $q,
        ], $items)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $shipment = $this->actions->create($this->tenant->idOrFail(), ['allocation' => 'value', 'expected_on' => null, 'original_amount' => null, 'notes' => null, ...$this->header($data)], $this->items($data));

        return response()->json(['data' => $this->view->full($shipment)], 201);
    }

    public function show(ImportShipment $shipment): JsonResponse
    {
        return response()->json(['data' => $this->view->full($shipment)]);
    }

    public function update(Request $request, ImportShipment $shipment): JsonResponse
    {
        $data = $this->validated($request, partial: true);
        $shipment = $this->actions->update($shipment, $this->header($data), isset($data['items']) ? $this->items($data) : null);

        return response()->json(['data' => $this->view->full($shipment)]);
    }

    public function move(Request $request, ImportShipment $shipment): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ShipmentStatus::class)]]);

        return response()->json(['data' => $this->view->full($this->actions->move($shipment, ShipmentStatus::from($data['status'])))]);
    }

    public function addCost(Request $request, ImportShipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ImportShipmentCost::KINDS))],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'contact_id' => ['nullable', 'uuid', Rule::exists('import_contacts', 'id')->where('tenant_id', $this->tenant->idOrFail())],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'المبلغ']);

        return response()->json(['data' => $this->view->full($this->actions->addCost($shipment, $data['kind'], (int) $data['amount'], $data['contact_id'] ?? null, $data['note'] ?? null))]);
    }

    public function removeCost(ImportShipment $shipment, ImportShipmentCost $cost): JsonResponse
    {
        return response()->json(['data' => $this->view->full($this->actions->removeCost($shipment, $cost))]);
    }

    public function receive(Request $request, ImportShipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'claim' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'max:500'],
            'lines.*.item_id' => ['required', 'uuid', 'distinct'],
            'lines.*.received' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.damaged' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.serials' => ['nullable', 'array', 'max:1000'],
            'lines.*.serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ]);
        $lines = array_values(array_map(fn (array $l) => [
            'item_id' => (string) $l['item_id'], 'received' => (int) $l['received'], 'damaged' => (int) ($l['damaged'] ?? 0), 'serials' => $l['serials'] ?? null,
        ], $data['lines']));

        return response()->json(['data' => $this->view->full($this->actions->receive($request->user() ?? abort(401), $shipment, $lines, (bool) ($data['claim'] ?? true)))]);
    }

    public function cancel(Request $request, ImportShipment $shipment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'السبب']);

        return response()->json(['data' => $this->view->full($this->actions->cancel($shipment, $data['reason']))]);
    }

    public function upload(Request $request, ImportShipment $shipment, ImportFiles $files): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ImportAttachment::KINDS))],
            'file' => ['required', 'file', 'mimetypes:'.implode(',', array_keys(ImportFiles::ATTACHMENT_TYPES)), 'max:10240'],
        ], [], ['file' => 'الملف']);
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $shipment->attachments()->create([
            'tenant_id' => $shipment->tenant_id,
            'kind' => $data['kind'],
            'name' => mb_substr($file->getClientOriginalName() ?: ImportAttachment::KINDS[$data['kind']], 0, 160),
            'path' => $files->putAttachment($shipment->tenant_id, $shipment->id, $file, $mime),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'uploaded_by_name' => $request->user()?->getAttribute('name'),
            'created_at' => now(),
        ]);

        return response()->json(['data' => $this->view->full($shipment->refresh())], 201);
    }

    public function attachment(ImportShipment $shipment, ImportAttachment $attachment, ImportFiles $files): BinaryFileResponse
    {
        abort_unless($attachment->shipment_id === $shipment->id, 404);
        $path = $files->attachment($attachment) ?? abort(404);

        return response()->file($path, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.rawurlencode($attachment->name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function removeAttachment(ImportShipment $shipment, ImportAttachment $attachment, ImportFiles $files): Response
    {
        abort_unless($attachment->shipment_id === $shipment->id, 404);
        $files->delete($attachment);
        $attachment->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $tenantId = $this->tenant->idOrFail();
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'contact_id' => [$required, 'uuid', Rule::exists('import_contacts', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => [$required, 'uuid', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'ordered_on' => [$required, 'date'],
            'expected_on' => ['nullable', 'date'],
            'allocation' => ['sometimes', 'in:value,qty,weight'],
            'original_amount' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => [$required, 'array', 'min:1', 'max:500'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:100000000000'],
            // The line's total weight in grams.
            'items.*.weight' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [], ['contact_id' => 'المورد', 'branch_id' => 'الفرع', 'items' => 'الأصناف', 'items.*.qty' => 'الكمية', 'items.*.unit_price' => 'السعر', 'items.*.weight' => 'الوزن']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function header(array $data): array
    {
        return array_intersect_key($data, array_flip(['contact_id', 'branch_id', 'ordered_on', 'expected_on', 'allocation', 'original_amount', 'notes']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{variant_id: string, qty: int, unit_price: int, weight: int|null}>
     */
    private function items(array $data): array
    {
        return array_values(array_map(fn (array $i) => ['variant_id' => (string) $i['variant_id'], 'qty' => (int) $i['qty'], 'unit_price' => (int) $i['unit_price'], 'weight' => isset($i['weight']) ? (int) $i['weight'] : null], $data['items']));
    }
}
