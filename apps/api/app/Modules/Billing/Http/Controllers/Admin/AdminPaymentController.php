<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\BillingView;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Identity\Contracts\PlatformShops;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Platform admins check InstaPay transfers: approve (the subscription is extended) or reject. */
final class AdminPaymentController
{
    public function __construct(
        private readonly BillingView $view,
        private readonly PlatformShops $shops,
        private readonly AdminLog $log,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');
        $requests = PaymentRequest::withoutTenancy()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy($status === 'pending' ? 'created_at' : 'updated_at', $status === 'pending' ? 'asc' : 'desc')
            ->limit(100)
            ->get();
        $shops = $this->shops->details($requests->pluck('tenant_id')->unique()->values()->all());

        return response()->json(['data' => $requests->map(fn (PaymentRequest $r) => [...$this->view->request($r), 'shop' => $shops[$r->tenant_id] ?? null])->values()]);
    }

    public function proof(string $paymentRequest): StreamedResponse
    {
        $payment = PaymentRequest::withoutTenancy()->findOrFail($paymentRequest);
        abort_if($payment->proof_path === null || ! Storage::disk('local')->exists($payment->proof_path), 404);
        $this->log->record('proof_viewed', $payment->tenant_id, $payment->id);

        return Storage::disk('local')->response($payment->proof_path);
    }

    public function approve(Request $request, string $paymentRequest, Subscriptions $subscriptions, Pricing $pricing): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = DB::transaction(function () use ($paymentRequest, $data, $request, $subscriptions, $pricing): PaymentRequest {
            $payment = PaymentRequest::withoutTenancy()->lockForUpdate()->findOrFail($paymentRequest);
            if ($payment->status !== 'pending') {
                throw new DomainRuleException('الطلب ده اتراجع خلاص.', 'request_not_pending');
            }
            $admin = $request->user();
            $quote = $pricing->quote($payment->plan, $payment->cycle, $payment->modules);
            $invoice = $subscriptions->activate($payment->tenant_id, $quote, 'instapay', $payment->reference, $admin?->getAttribute('name'), $data['amount'] ?? $payment->amount, $data['note'] ?? null);
            $payment->update([
                'status' => 'approved',
                'reviewed_by' => $admin?->getAuthIdentifier(),
                'reviewed_by_name' => $admin?->getAttribute('name'),
                'reviewed_at' => now(),
                'invoice_id' => $invoice->id,
            ]);

            $this->log->record('payment_approved', $payment->tenant_id, $payment->id, ['reference' => $payment->reference, 'amount' => $invoice->total]);

            return $payment;
        });

        return response()->json(['data' => $this->view->request($payment)]);
    }

    public function reject(Request $request, string $paymentRequest, CurrentTenant $current, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], attributes: ['reason' => 'السبب']);
        $payment = PaymentRequest::withoutTenancy()->findOrFail($paymentRequest);
        if ($payment->status !== 'pending') {
            throw new DomainRuleException('الطلب ده اتراجع خلاص.', 'request_not_pending');
        }
        $admin = $request->user();
        $payment->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $admin?->getAuthIdentifier(),
            'reviewed_by_name' => $admin?->getAttribute('name'),
            'reviewed_at' => now(),
        ]);
        $this->log->record('payment_rejected', $payment->tenant_id, $payment->id, ['reference' => $payment->reference, 'reason' => $data['reason']]);
        $current->runAs($payment->tenant_id, fn () => $audit->record('billing.payment_rejected', "الإدارة رفضت طلب الدفع (رقم العملية {$payment->reference}): {$data['reason']}", $payment, tenantId: $payment->tenant_id));

        return response()->json(['data' => $this->view->request($payment)]);
    }
}
