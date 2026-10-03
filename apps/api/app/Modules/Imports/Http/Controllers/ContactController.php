<?php

declare(strict_types=1);

namespace App\Modules\Imports\Http\Controllers;

use App\Modules\Imports\Models\ImportContact;
use App\Modules\Imports\Models\ImportContactTransaction;
use App\Modules\Imports\Models\ImportPayment;
use App\Modules\Imports\Models\ImportShipment;
use App\Modules\Imports\Support\ShipmentView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «جهات الاستيراد»: suppliers, agents, shipping companies, customs brokers — and their statements. */
final class ContactController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(array_keys(ImportContact::TYPES))], 'q' => ['nullable', 'string', 'max:60']]);

        return response()->json(['data' => ImportContact::query()
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->when(filled($data['q'] ?? null), fn ($q) => $q->where('name', 'ilike', '%'.addcslashes((string) $data['q'], '%_\\').'%'))
            ->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (ImportContact $c) => $c->toApi())->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => ImportContact::create($this->validated($request))->toApi()], 201);
    }

    public function update(Request $request, ImportContact $contact): JsonResponse
    {
        $contact->update($this->validated($request, partial: true));

        return response()->json(['data' => $contact->toApi()]);
    }

    /** The contact with its statement, shipments and payments. */
    public function show(ImportContact $contact, ShipmentView $view): JsonResponse
    {
        return response()->json(['data' => [
            ...$contact->toApi(),
            'statement' => ImportContactTransaction::query()->where('contact_id', $contact->id)->orderByDesc('seq')->limit(300)->get()
                ->map(fn (ImportContactTransaction $t) => [
                    'id' => $t->id, 'type' => $t->type, 'type_label' => ImportContactTransaction::TYPES[$t->type] ?? $t->type,
                    'amount' => $t->amount, 'balance_after' => $t->balance_after, 'ref_type' => $t->ref_type, 'ref_id' => $t->ref_id,
                    'note' => $t->note, 'user_name' => $t->user_name, 'created_at' => $t->created_at->toIso8601String(),
                ])->all(),
            'shipments' => ImportShipment::query()->where('contact_id', $contact->id)->orderByDesc('created_at')->limit(100)->get()
                ->map(fn (ImportShipment $s) => $view->row($s))->all(),
            'payments' => ImportPayment::query()->where('contact_id', $contact->id)->orderByDesc('paid_on')->orderByDesc('created_at')->limit(100)->get()
                ->map(fn (ImportPayment $p) => $p->toApi())->all(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$required, Rule::in(array_keys(ImportContact::TYPES))],
            'name' => [$required, 'string', 'min:2', 'max:120'],
            'country' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'wechat' => ['nullable', 'string', 'max:60'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'الاسم', 'type' => 'النوع']);
    }
}
