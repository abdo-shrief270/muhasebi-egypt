<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Tenant;

/**
 * What the shop prints on its receipts besides its name and phone (tenants.settings.receipt):
 * tax registration number, commercial register number and a closing line.
 */
final class ReceiptSettings
{
    public const DEFAULT_FOOTER = 'شكراً لزيارتك 🌷';

    /** @return array{tax_number: string|null, commercial_register: string|null, footer: string} */
    public static function of(Tenant $tenant): array
    {
        $saved = (array) (($tenant->settings ?? [])['receipt'] ?? []);

        return [
            'tax_number' => self::text($saved['tax_number'] ?? null),
            'commercial_register' => self::text($saved['commercial_register'] ?? null),
            'footer' => self::text($saved['footer'] ?? null) ?? self::DEFAULT_FOOTER,
        ];
    }

    /** @param  array{tax_number?: string|null, commercial_register?: string|null, footer?: string|null}  $values */
    public static function apply(Tenant $tenant, array $values): void
    {
        $settings = $tenant->settings ?? [];
        $settings['receipt'] = [
            'tax_number' => self::text($values['tax_number'] ?? null),
            'commercial_register' => self::text($values['commercial_register'] ?? null),
            'footer' => self::text($values['footer'] ?? null),
        ];
        $tenant->settings = $settings;
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
