<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «كتالوج الموبايلات»: the platform's reference list of phones, kept by the platform admins. */
final class AdminPhoneController
{
    public function __construct(
        private readonly PhoneCatalog $phones,
        private readonly AdminLog $log,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'brand' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(PhoneCatalog::STATUSES)],
        ]);

        return response()->json(['data' => $this->phones->browse($filters)]);
    }

    public function store(Request $request): JsonResponse
    {
        $phone = $this->phones->save(null, $this->validated($request, true), $request->user()?->getAttribute('name'));
        $this->log->record('phone_added', null, $phone['id'], ['phone' => "{$phone['brand']} {$phone['model']}"]);

        return response()->json(['data' => $phone], 201);
    }

    public function update(Request $request, string $phone): JsonResponse
    {
        abort_if($this->phones->find($phone) === null, 404);
        $data = $this->validated($request, false);
        $saved = $this->phones->save($phone, $data, $request->user()?->getAttribute('name'));
        $this->log->record('phone_changed', null, $phone, ['phone' => "{$saved['brand']} {$saved['model']}", ...array_intersect_key($data, array_flip(['status', 'year', 'network']))]);

        return response()->json(['data' => $saved]);
    }

    public function destroy(string $phone): JsonResponse
    {
        $found = $this->phones->find($phone);
        abort_if($found === null, 404);
        $this->phones->delete($phone);
        $this->log->record('phone_deleted', null, $phone, ['phone' => "{$found['brand']} {$found['model']}"]);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'brand' => [$required, 'string', 'max:40'],
            'model' => [$required, 'string', 'max:80'],
            'series' => ['sometimes', 'nullable', 'string', 'max:40'],
            'aliases' => ['sometimes', 'array', 'max:20'],
            'aliases.*' => ['nullable', 'string', 'max:80'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'storage_gb' => ['sometimes', 'array', 'max:10'],
            'storage_gb.*' => ['integer', Rule::in([4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048])],
            'network' => ['sometimes', 'nullable', Rule::in(PhoneCatalog::NETWORKS)],
            'status' => ['sometimes', Rule::in(PhoneCatalog::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
    }
}
