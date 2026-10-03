<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\OnlineStore\Actions\PlaceOrderAction;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\OnlineStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Propaganistas\LaravelPhone\PhoneNumber;

/** The customer's side: placing an order on the store, and its tracking page /o/{token}. */
final class PublicOrderController
{
    public function store(Request $request, PlaceOrderAction $place): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'phone:EG,mobile'],
            'fulfilment' => ['required', 'in:pickup,delivery'],
            'zone_id' => ['nullable', 'uuid'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment' => ['required', 'in:cod,transfer'],
            'proof' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'consent' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            // Honeypot: a field people never see; bots fill it.
            'website' => ['nullable', 'max:0'],
        ], [], [
            'name' => 'الاسم', 'phone' => 'رقم الموبايل', 'address' => 'العنوان', 'proof' => 'صورة التحويل', 'items' => 'السلة',
        ]);

        $order = $place->handle(
            store: $this->onlineStore($request),
            orderId: $data['id'] ?? null,
            name: trim($data['name']),
            phone: (new PhoneNumber($data['phone'], 'EG'))->formatE164(),
            fulfilment: $data['fulfilment'],
            zoneId: $data['zone_id'] ?? null,
            address: isset($data['address']) ? trim($data['address']) : null,
            notes: isset($data['notes']) ? trim($data['notes']) : null,
            payment: $data['payment'],
            proof: $request->file('proof'),
            consent: (bool) ($data['consent'] ?? false),
            items: array_values(array_map(fn (array $i) => ['variant_id' => (string) $i['variant_id'], 'qty' => (int) $i['qty']], $data['items'])),
        );

        return response()->json(['data' => [...$order->load(['items', 'events'])->toPublic(), 'token' => $order->token]], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $slug, string $token): JsonResponse
    {
        $order = OnlineOrder::query()->where('token', $token)->with(['items', 'events'])->first();
        if ($order === null) {
            return response()->json(['message' => 'الطلب ده مش موجود.', 'code' => 'order_not_found'], 404);
        }

        return response()->json(['data' => $order->toPublic()])->header('Cache-Control', 'no-store');
    }

    private function onlineStore(Request $request): OnlineStore
    {
        return $request->attributes->get('online_store');
    }
}
