<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\BillingView;
use App\Modules\Billing\Support\Checkout;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Billing\Support\Wallet;
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

    /**
     * The transfer arrived: the subscription is extended. The invoice carries the discount the
     * request was priced with; its total = the transfer + the credit held with the request.
     */
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
            $redemption = null;
            if ($payment->discount > 0) {
                $quote['lines'][] = ['description' => 'خصم (كوبون / دعوة)', 'amount' => -$payment->discount];
                $quote['total'] -= $payment->discount;
                if ($payment->redemption_id !== null) {
                    [, , $months] = app(Checkout::class)->discount($payment->tenant_id, ['total' => $quote['total'] + $payment->discount, 'months' => $quote['months']], $payment->redemption_id);
                    $redemption = ['id' => $payment->redemption_id, 'months' => max(1, $months)];
                }
            }
            $received = $data['amount'] ?? $payment->amount;
            $invoice = $subscriptions->activate(
                $payment->tenant_id, $quote, 'instapay', $payment->reference, $admin?->getAttribute('name'),
                $received + $payment->credit_used, $data['note'] ?? null, creditUsed: $payment->credit_used, redemption: $redemption,
            );
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

    public function reject(Request $request, string $paymentRequest, CurrentTenant $current, Auditor $audit, Wallet $wallet): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], attributes: ['reason' => 'السبب']);
        $admin = $request->user();
        $payment = DB::transaction(function () use ($paymentRequest, $data, $admin, $wallet): PaymentRequest {
            $payment = PaymentRequest::withoutTenancy()->lockForUpdate()->findOrFail($paymentRequest);
            if ($payment->status !== 'pending') {
                throw new DomainRuleException('الطلب ده اتراجع خلاص.', 'request_not_pending');
            }
            $payment->update([
                'status' => 'rejected',
                'rejection_reason' => $data['reason'],
                'reviewed_by' => $admin?->getAuthIdentifier(),
                'reviewed_by_name' => $admin?->getAttribute('name'),
                'reviewed_at' => now(),
            ]);
            // The credit held with it goes back.
            if ($payment->credit_used > 0) {
                $wallet->post($payment->tenant_id, 'credit', 'refund', $payment->credit_used, 'payment_request', $payment->id, 'طلب الدفع اترفض');
            }

            return $payment;
        });
        $this->log->record('payment_rejected', $payment->tenant_id, $payment->id, ['reference' => $payment->reference, 'reason' => $data['reason']]);
        $current->runAs($payment->tenant_id, fn () => $audit->record('billing.payment_rejected', "الإدارة رفضت طلب الدفع (رقم العملية {$payment->reference}): {$data['reason']}", $payment, tenantId: $payment->tenant_id));

        return response()->json(['data' => $this->view->request($payment)]);
    }
}
