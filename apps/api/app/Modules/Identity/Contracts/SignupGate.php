<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * Whether new shops may register on their own. Closed, the sign-up form gives way to a WhatsApp
 * chat with the team, and POST /auth/register is refused. Platform admins switch it.
 */
interface SignupGate
{
    /** @return array{open: bool, whatsapp: string|null, message: string} */
    public function get(): array;

    /**
     * @param  array{open: bool, whatsapp?: string|null, message?: string|null}  $values
     * @return array{open: bool, whatsapp: string|null, message: string}
     */
    public function save(array $values): array;
}
