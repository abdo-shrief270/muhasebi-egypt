<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Concerns;

use Propaganistas\LaravelPhone\PhoneNumber;

trait NormalizesPhone
{
    /** Store Egyptian numbers as E.164 (+2010…) whatever format was typed. */
    protected function normalizePhone(string $field = 'phone'): void
    {
        $phone = $this->input($field);

        if (is_string($phone) && $phone !== '') {
            try {
                $this->merge([$field => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // Leave as-is; the phone rule reports it.
            }
        }
    }
}
