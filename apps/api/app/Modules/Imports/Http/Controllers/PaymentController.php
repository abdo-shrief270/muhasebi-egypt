<?php

declare(strict_types=1);

namespace App\Modules\Imports\Http\Controllers;

use App\Modules\Imports\Actions\PaymentActions;
use App\Modules\Imports\Models\ImportPayment;
use App\Modules\Imports\Support\ImportFiles;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Money sent to import contacts, with the transfer receipt. */
final class PaymentController
{
    public function __construct(private readonly PaymentActions $actions) {}

    public function store(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $tenantId = $tenant->idOrFail();
        $data = $request->validate([
            'contact_id' => ['required', 'uuid', Rule::exists('import_contacts', 'id')->where('tenant_id', $tenantId)],
            'shipment_id' => ['nullable', 'uuid'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'method' => ['required', Rule::in(array_keys(ImportPayment::METHODS))],
            'paid_on' => ['required', 'date', 'before_or_equal:tomorrow'],
            'received_by' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [], ['amount' => 'المبلغ', 'method' => 'طريقة الدفع', 'paid_on' => 'التاريخ', 'proof' => 'صورة الإيصال']);

        $payment = $this->actions->pay($request->user() ?? abort(401), $tenantId, [
            'contact_id' => $data['contact_id'],
            'shipment_id' => $data['shipment_id'] ?? null,
            'amount' => (int) $data['amount'],
            'method' => $data['method'],
            'paid_on' => $data['paid_on'],
            'received_by' => $data['received_by'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
        ], $request->file('proof'));

        return response()->json(['data' => $payment->toApi()], 201);
    }

    public function reverse(Request $request, ImportPayment $payment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'السبب']);

        return response()->json(['data' => $this->actions->reverse($payment, $data['reason'])->toApi()]);
    }

    public function proof(ImportPayment $payment, ImportFiles $files): BinaryFileResponse
    {
        $path = $files->proof($payment) ?? abort(404);

        return response()->file($path, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }
}
