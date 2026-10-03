<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Tenant;

/**
 * What the shop prints on its receipts besides its name and phone (tenants.settings.receipt):
 * tax registration number, commercial register number, a closing line, and whether the cashier,
 * the customer and the IMEI / serials of what was sold are printed (all on unless switched off).
 */
final class ReceiptSettings
{
    public const DEFAULT_FOOTER = 'شكراً لزيارتك 🌷';

    public const SWITCHES = ['show_cashier', 'show_customer', 'show_serials'];

    /** @return array{tax_number: string|null, commercial_register: string|null, footer: string, show_cashier: bool, show_customer: bool, show_serials: bool} */
    public static function of(Tenant $tenant): array
    {
        $saved = (array) (($tenant->settings ?? [])['receipt'] ?? []);

        return [
            'tax_number' => self::text($saved['tax_number'] ?? null),
            'commercial_register' => self::text($saved['commercial_register'] ?? null),
            'footer' => self::text($saved['footer'] ?? null) ?? self::DEFAULT_FOOTER,
            'show_cashier' => (bool) ($saved['show_cashier'] ?? true),
            'show_customer' => (bool) ($saved['show_customer'] ?? true),
            'show_serials' => (bool) ($saved['show_serials'] ?? true),
        ];
    }

    /** @param  array{tax_number?: string|null, commercial_register?: string|null, footer?: string|null, show_cashier?: bool, show_customer?: bool, show_serials?: bool}  $values  a switch left out keeps its value */
    public static function apply(Tenant $tenant, array $values): void
    {
        $settings = $tenant->settings ?? [];
        $current = self::of($tenant);
        $settings['receipt'] = [
            'tax_number' => self::text($values['tax_number'] ?? null),
            'commercial_register' => self::text($values['commercial_register'] ?? null),
            'footer' => self::text($values['footer'] ?? null),
        ];
        foreach (self::SWITCHES as $switch) {
            $settings['receipt'][$switch] = (bool) ($values[$switch] ?? $current[$switch]);
        }
        $tenant->settings = $settings;
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
