<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

/** DomainDns through the system resolver. */
final class SystemDomainDns implements DomainDns
{
    public function txt(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT) ?: [];

        return array_values(array_map(fn (array $r) => (string) ($r['txt'] ?? implode('', $r['entries'] ?? [])), $records));
    }

    public function ips(string $name): array
    {
        $ips = @gethostbynamel($name);

        return $ips === false ? [] : array_values($ips);
    }

    public function cname(string $name): ?string
    {
        $records = @dns_get_record($name, DNS_CNAME) ?: [];

        return isset($records[0]['target']) ? rtrim(strtolower((string) $records[0]['target']), '.') : null;
    }
}
