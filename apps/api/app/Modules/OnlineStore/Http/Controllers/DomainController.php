<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Support\CustomDomains;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shop's own domain for its store (online_store.manage): set it, add the DNS records it shows,
 * then «اتأكد» checks them. Changing or removing it stops serving the old one at once.
 */
final class DomainController
{
    public function __construct(
        private readonly CustomDomains $domains,
        private readonly Auditor $audit,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present($this->store())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:300']], [], ['domain' => 'الدومين']);
        $store = $this->store();
        $domain = CustomDomains::normalize($data['domain']);
        if ($domain === $store->custom_domain) {
            return response()->json(['data' => $this->present($store)]);
        }
        if (OnlineStore::withoutTenancy()->where('custom_domain', $domain)->where('id', '!=', $store->id)->exists()) {
            throw new DomainRuleException('الدومين ده متسجل لمحل تاني. لو هو بتاعك كلّمنا.', 'domain_taken');
        }
        $store->update(['custom_domain' => $domain, 'custom_domain_token' => CustomDomains::newToken(), 'custom_domain_verified_at' => null]);
        $this->audit->record('online_store.domain', "ربط المتجر بالدومين {$domain}", $store);

        return response()->json(['data' => $this->present($store)]);
    }

    /** «اتأكد»: the TXT record proves it's theirs; until it points to us it isn't served yet. */
    public function verify(): JsonResponse
    {
        $store = $this->store();
        if ($store->custom_domain === null) {
            throw new DomainRuleException('اكتب الدومين الأول.', 'domain_missing');
        }
        $check = $this->domains->check($store);
        if ($check['owned'] && $store->custom_domain_verified_at === null) {
            $store->update(['custom_domain_verified_at' => now()]);
            $this->audit->record('online_store.domain_verified', "اتأكد الدومين {$store->custom_domain}", $store);
        }

        return response()->json(['data' => [...$this->present($store), 'points' => $check['points'], 'owned' => $check['owned']]]);
    }

    public function destroy(): JsonResponse
    {
        $store = $this->store();
        if ($store->custom_domain !== null) {
            $this->audit->record('online_store.domain_removed', "شال الدومين {$store->custom_domain} من المتجر", $store);
        }
        $store->update(['custom_domain' => null, 'custom_domain_token' => null, 'custom_domain_verified_at' => null]);

        return response()->json(['data' => $this->present($store)]);
    }

    private function store(): OnlineStore
    {
        return OnlineStore::query()->first() ?? throw new DomainRuleException('افتح صفحة المتجر الأول.', 'store_missing', 404);
    }

    /** @return array<string, mixed> */
    private function present(OnlineStore $store): array
    {
        $domain = $store->custom_domain;

        return [
            'domain' => $domain,
            'verified' => $store->custom_domain_verified_at !== null,
            'verified_at' => $store->custom_domain_verified_at?->toIso8601String(),
            // What to add at the domain's DNS provider.
            'records' => $domain === null ? [] : array_values(array_filter([
                ['type' => 'TXT', 'name' => CustomDomains::txtName($domain), 'value' => CustomDomains::txtValue((string) $store->custom_domain_token)],
                CustomDomains::target($store) !== null ? ['type' => 'CNAME', 'name' => $domain, 'value' => CustomDomains::target($store)] : null,
            ])),
            'url' => $store->url(),
        ];
    }
}
