<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

/** DNS lookups for custom domains (faked in tests). */
interface DomainDns
{
    /** @return list<string> the TXT records of $name */
    public function txt(string $name): array;

    /** @return list<string> the IPv4 addresses $name resolves to (following CNAMEs) */
    public function ips(string $name): array;

    /** The CNAME target of $name, if it has one. */
    public function cname(string $name): ?string;
}
