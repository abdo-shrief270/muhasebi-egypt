<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\OnlineStore\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The store's delivery zones and their fees (online_store.manage). */
final class DeliveryZoneController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        DeliveryZone::create([...$data, 'sort' => (int) DeliveryZone::query()->max('sort') + 1]);

        return response()->json(['data' => $this->all()], 201);
    }

    public function update(Request $request, DeliveryZone $zone): JsonResponse
    {
        $zone->update($this->validated($request, partial: true));

        return response()->json(['data' => $this->all()]);
    }

    public function destroy(DeliveryZone $zone): Response
    {
        $zone->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:80'],
            'fee' => [$required, 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'اسم المنطقة', 'fee' => 'مصاريف التوصيل']);
    }

    /** @return list<array<string, mixed>> */
    private function all(): array
    {
        return DeliveryZone::query()->orderBy('sort')->orderBy('name')->get()->map(fn (DeliveryZone $z) => $z->toApi())->all();
    }
}
