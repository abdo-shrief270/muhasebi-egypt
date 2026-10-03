<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\OnlineStore\Actions\MoveOrderAction;
use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Support\ProofStore;
use App\Modules\OnlineStore\Support\Slugs;
use App\Support\Text\SearchText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** «طلبات المتجر» (online_store.orders): the orders customers placed, moved along by the shop. */
final class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(OrderStatus::cases(), 'value'), 'open'])],
            'q' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $q = trim((string) ($data['q'] ?? ''));
        $page = OnlineOrder::query()
            ->with('items')
            ->when(($data['status'] ?? null) === 'open', fn ($b) => $b->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled]))
            ->when(($data['status'] ?? 'open') !== 'open', fn ($b) => $b->where('status', $data['status']))
            ->when($q !== '', function ($b) use ($q): void {
                $digits = preg_replace('/\D/', '', $q);
                $b->where(function ($w) use ($q, $digits): void {
                    $w->where('customer_name', 'ilike', SearchText::like($q));
                    if ($digits !== '') {
                        $w->orWhere('customer_phone', 'like', '%'.ltrim($digits, '0').'%')->orWhere('number', (int) $digits);
                    }
                });
            })
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (OnlineOrder $o) => $o->toApi())->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'counts' => $this->counts()],
        ]);
    }

    public function show(OnlineOrder $order): JsonResponse
    {
        return response()->json(['data' => $this->present($order)]);
    }

    public function move(Request $request, OnlineOrder $order, MoveOrderAction $move): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['reason' => 'السبب']);

        return response()->json(['data' => $this->present($move->handle($order, OrderStatus::from($data['status']), $data['reason'] ?? null))]);
    }

    /** Orders waiting for the shop (the menu badge). */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->counts()]);
    }

    public function proof(OnlineOrder $order, ProofStore $proofs): BinaryFileResponse
    {
        $file = $proofs->file($order);
        abort_if($file === null, 404);

        return response()->file($file, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    private function present(OnlineOrder $order): array
    {
        $store = OnlineStore::query()->first();

        return [
            ...$order->load(['items', 'events'])->toApi(true),
            'track_url' => $store !== null ? Slugs::url($store->slug).'/o/'.$order->token : null,
        ];
    }

    /** @return array{new: int, open: int} */
    private function counts(): array
    {
        $rows = OnlineOrder::query()->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return ['new' => (int) ($rows[OrderStatus::New->value] ?? 0), 'open' => (int) $rows->sum()];
    }
}
