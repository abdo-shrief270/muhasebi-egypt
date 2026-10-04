<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Models\OnlineStore;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Str;

/**
 * A shop's own domain for its store. It's theirs when `_muhasebi.<domain>` has the TXT record
 * `muhasebi-verify=<token>`; it reaches us when it's a CNAME to {slug}.<STORE_HOST> or resolves to
 * the same addresses. Only a verified domain is served (and gets a certificate).
 */
final class CustomDomains
{
    public const TXT_PREFIX = '_muhasebi.';

    public function __construct(private readonly DomainDns $dns) {}

    /** "https://WWW.Shop.com/path" → "www.shop.com", or why not. */
    public static function normalize(string $input): string
    {
        $host = strtolower(trim($input));
        $host = (string) preg_replace('#^[a-z]+://#', '', $host);
        $host = rtrim((string) preg_replace('#[/?\#:].*$#', '', $host), '.');
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $host = (string) idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }
        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        if (strlen($host) > 253 || preg_match("/^(?:{$label}\\.)+[a-z][a-z0-9-]{1,62}$/", $host) !== 1) {
            throw new DomainRuleException('اكتب الدومين بس، زي www.elnour-mobile.com.', 'domain_invalid');
        }
        foreach (self::platformHosts() as $ours) {
            if ($host === $ours || str_ends_with($host, '.'.$ours)) {
                throw new DomainRuleException('ده دومين المنصة نفسها؛ اكتب دومين محلك.', 'domain_platform');
            }
        }

        return $host;
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(32));
    }

    public static function txtName(string $domain): string
    {
        return self::TXT_PREFIX.$domain;
    }

    public static function txtValue(string $token): string
    {
        return 'muhasebi-verify='.$token;
    }

    /** The name a CNAME should point at: the store's own subdomain, when stores have one. */
    public static function target(OnlineStore $store): ?string
    {
        $host = strtolower((string) config('services.store.host'));

        return $host !== '' ? "{$store->slug}.{$host}" : null;
    }

    /** @return array{owned: bool, points: bool} the TXT record is there / the domain reaches us */
    public function check(OnlineStore $store): array
    {
        $domain = (string) $store->custom_domain;
        $owned = in_array(self::txtValue((string) $store->custom_domain_token), array_map('trim', $this->dns->txt(self::txtName($domain))), true);

        return ['owned' => $owned, 'points' => $this->points($store)];
    }

    private function points(OnlineStore $store): bool
    {
        $target = self::target($store);
        if ($target === null) {
            return false;
        }
        $cname = $this->dns->cname((string) $store->custom_domain);
        if ($cname !== null && ($cname === $target || str_ends_with($cname, '.'.strtolower((string) config('services.store.host'))))) {
            return true;
        }
        $ours = $this->dns->ips($target);
        $theirs = $this->dns->ips((string) $store->custom_domain);

        return $ours !== [] && $theirs !== [] && array_diff($theirs, $ours) === [];
    }

    /** @return list<string> hosts of the platform itself (never a shop's domain) */
    private static function platformHosts(): array
    {
        $hosts = [
            (string) config('services.store.host'),
            (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            (string) config('billing.admin.domain'),
            (string) parse_url(str_replace('{slug}', 'x', (string) config('services.store.url')), PHP_URL_HOST),
        ];

        return array_values(array_unique(array_filter(array_map('strtolower', $hosts), fn (string $h) => $h !== '' && $h !== 'localhost')));
    }
}
