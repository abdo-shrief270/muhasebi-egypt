<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Support\BillingView;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Billing\Support\Subscriptions;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shop's side: its subscription, the plans, paying by InstaPay (a request an admin approves)
 * and its invoices. Owner only, except the status every screen shows as a banner.
 */
final class BillingController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Subscriptions $subscriptions,
        private readonly BillingView $view,
        private readonly Pricing $pricing,
    ) {}

    /** For the banner on every screen (any signed-in user; listed in RouteAuthorizationTest). */
    public function status(): JsonResponse
    {
        return response()->json(['data' => $this->view->subscription($this->subscriptions->for($this->tenant->idOrFail()))]);
    }

    public function show(): JsonResponse
    {
        $tenantId = $this->tenant->idOrFail();

        return response()->json(['data' => [
            'subscription' => $this->view->subscription($this->subscriptions->for($tenantId)),
            'plans' => $this->view->catalog(),
            'extra_modules' => $this->view->extraModules(),
            'yearly_months' => (int) config('billing.yearly_months'),
            'instapay' => config('billing.instapay'),
            'requests' => PaymentRequest::query()->latest()->limit(10)->get()->map(fn (PaymentRequest $r) => $this->view->request($r))->all(),
            'invoices' => BillingInvoice::query()->orderByDesc('number')->get()->map(fn (BillingInvoice $i) => $this->view->invoice($i))->all(),
        ]]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->validateChoice($request);

        return response()->json(['data' => $this->pricing->quote($data['plan'], $data['cycle'], $data['modules'] ?? [])]);
    }

    /** "I paid by InstaPay": waits for an admin to check the transfer. */
    public function requestPayment(Request $request, Auditor $audit): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('owner'), 403);
        $data = $this->validateChoice($request) + $request->validate([
            'reference' => ['required', 'string', 'max:60'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], attributes: ['reference' => 'رقم العملية', 'proof' => 'صورة التحويل']);

        if (PaymentRequest::query()->where('status', 'pending')->exists()) {
            throw new DomainRuleException('عندك طلب دفع لسه بيتراجع. استنى الرد أو الغيه الأول.', 'request_pending');
        }
        $quote = $this->pricing->quote($data['plan'], $data['cycle'], $data['modules'] ?? []);
        $tenantId = $this->tenant->idOrFail();
        $user = $request->user();

        $payment = PaymentRequest::create([
            'tenant_id' => $tenantId,
            'plan' => $quote['plan'],
            'cycle' => $quote['cycle'],
            'modules' => $quote['modules'],
            'amount' => $quote['total'],
            'method' => 'instapay',
            'reference' => $data['reference'],
            'sender_name' => $data['sender_name'] ?? null,
            'sender_phone' => $data['sender_phone'] ?? null,
            'proof_path' => $request->file('proof')?->store("billing-proofs/{$tenantId}", 'local'),
            'status' => 'pending',
            'requested_by' => $user?->getAuthIdentifier(),
            'requested_by_name' => $user?->getAttribute('name'),
        ]);
        $audit->record('billing.payment_requested', 'بعت طلب دفع InstaPay بـ '.number_format($quote['total'] / 100, 2)." ج (رقم العملية {$payment->reference})", $payment);

        return response()->json(['data' => $this->view->request($payment)], Response::HTTP_CREATED);
    }

    public function cancelRequest(string $paymentRequest): JsonResponse
    {
        $payment = PaymentRequest::query()->findOrFail($paymentRequest);
        if ($payment->status !== 'pending') {
            throw new DomainRuleException('الطلب ده اتراجع خلاص.', 'request_not_pending');
        }
        $payment->update(['status' => 'cancelled']);

        return response()->json(['data' => $this->view->request($payment)]);
    }

    public function invoice(string $invoice): JsonResponse
    {
        return response()->json(['data' => $this->view->invoice(BillingInvoice::query()->findOrFail($invoice))]);
    }

    /** @return array{plan: string, cycle: string, modules?: list<string>} */
    private function validateChoice(Request $request): array
    {
        return $request->validate([
            'plan' => ['required', Rule::in(array_keys($this->pricing->plans()))],
            'cycle' => ['required', Rule::in(Pricing::CYCLES)],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys($this->pricing->modulePrices()))],
        ], attributes: ['plan' => 'الباقة', 'cycle' => 'مدة الاشتراك']);
    }
}
