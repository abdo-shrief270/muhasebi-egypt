<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\BillingView;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Billing\Support\SubscriptionStatus;
use App\Modules\Identity\Contracts\PlatformShops;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platform admins: the shops, their subscriptions, and activating / extending / suspending them. */
final class AdminShopController
{
    public function __construct(
        private readonly PlatformShops $shops,
        private readonly Subscriptions $subscriptions,
        private readonly BillingView $view,
        private readonly Pricing $pricing,
    ) {}

    public function overview(): JsonResponse
    {
        $counts = [];
        foreach (SubscriptionStatus::cases() as $status) {
            $counts[$status->value] = Subscription::withoutTenancy()->withStatus($status)->count();
        }
        $mrr = Subscription::withoutTenancy()->withStatus(SubscriptionStatus::Active)->get()->sum(fn (Subscription $s) => $this->view->monthlyValue($s));

        return response()->json(['data' => [
            'counts' => $counts,
            'shops' => Subscription::withoutTenancy()->count(),
            'pending_payments' => PaymentRequest::withoutTenancy()->where('status', 'pending')->count(),
            'expiring_soon' => Subscription::withoutTenancy()->whereNull('suspended_at')->whereBetween('paid_until', [now(), now()->addDays(7)])->count(),
            'mrr' => $mrr,
            'collected_this_month' => (int) BillingInvoice::withoutTenancy()->where('paid_at', '>=', now()->startOfMonth())->sum('total'),
            'plans' => $this->view->catalog(),
            'extra_modules' => $this->view->extraModules(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
        ]);
        $page = Subscription::withoutTenancy()
            ->when(filled($data['q'] ?? null), fn ($q) => $q->whereIn('tenant_id', $this->shops->searchIds((string) $data['q'])))
            ->when(isset($data['status']), fn ($q) => $q->withStatus(SubscriptionStatus::from($data['status'])))
            ->orderBy('paid_until')
            ->paginate(30);
        $details = $this->shops->details($page->getCollection()->pluck('tenant_id')->all());

        return response()->json([
            'data' => $page->getCollection()->map(fn (Subscription $s) => ['shop' => $details[$s->tenant_id] ?? null, 'subscription' => $this->view->subscription($s)])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(string $tenant): JsonResponse
    {
        $shop = $this->shops->details([$tenant])[$tenant] ?? abort(404);

        return response()->json(['data' => [
            'shop' => $shop,
            'subscription' => $this->view->subscription($this->subscriptions->for($tenant)),
            'requests' => PaymentRequest::withoutTenancy()->where('tenant_id', $tenant)->latest()->get()->map(fn (PaymentRequest $r) => $this->view->request($r))->all(),
            'invoices' => BillingInvoice::withoutTenancy()->where('tenant_id', $tenant)->orderByDesc('number')->get()->map(fn (BillingInvoice $i) => $this->view->invoice($i))->all(),
        ]]);
    }

    /** Activate or extend directly (paid in cash, a gift, a deal): writes an invoice like a payment would. */
    public function activate(Request $request, string $tenant): JsonResponse
    {
        $this->shops->details([$tenant])[$tenant] ?? abort(404);
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys($this->pricing->plans()))],
            'cycle' => ['required', Rule::in(Pricing::CYCLES)],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys($this->pricing->modulePrices()))],
            'months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $quote = $this->pricing->quote($data['plan'], $data['cycle'], $data['modules'] ?? []);
        $this->subscriptions->activate($tenant, $quote, 'manual', $data['reference'] ?? null, $request->user()?->getAttribute('name'), $data['amount'] ?? null, $data['note'] ?? null, $data['months'] ?? null);

        return $this->show($tenant);
    }

    /** More trial days (e.g. a shop still setting up). */
    public function extendTrial(Request $request, string $tenant, CurrentTenant $current, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']]);
        $subscription = $this->subscriptions->for($tenant);
        abort_unless($subscription->on_trial, 422, 'الاشتراك مدفوع؛ استخدم التفعيل.');
        $subscription->update(['paid_until' => now()->max($subscription->paid_until)->addDays($data['days'])]);
        $current->runAs($tenant, fn () => $audit->record('billing.trial_extended', "الإدارة مدّت التجربة {$data['days']} يوم", $subscription, tenantId: $tenant));
        $this->subscriptions->forget($tenant);

        return $this->show($tenant);
    }

    public function suspend(Request $request, string $tenant, CurrentTenant $current, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $subscription = $this->subscriptions->for($tenant);
        $subscription->update(['suspended_at' => now(), 'suspended_reason' => $data['reason']]);
        $current->runAs($tenant, fn () => $audit->record('billing.suspended', "الإدارة وقّفت الاشتراك: {$data['reason']}", $subscription, tenantId: $tenant));
        $this->subscriptions->forget($tenant);

        return $this->show($tenant);
    }

    public function unsuspend(string $tenant, CurrentTenant $current, Auditor $audit): JsonResponse
    {
        $subscription = $this->subscriptions->for($tenant);
        $subscription->update(['suspended_at' => null, 'suspended_reason' => null]);
        $current->runAs($tenant, fn () => $audit->record('billing.unsuspended', 'الإدارة رجّعت الاشتراك', $subscription, tenantId: $tenant));
        $this->subscriptions->forget($tenant);

        return $this->show($tenant);
    }
}
