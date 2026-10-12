<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Tenant;

/**
 * What the shop prints on its receipts besides its name and phone (tenants.settings.receipt):
 * tax registration number, commercial register number, a closing line, and whether the cashier,
 * the customer and the IMEI / serials of what was sold are printed (all on unless switched off),
 * plus the default label rolls: product labels and the device sticker (a device may pick another).
 */
final class ReceiptSettings
{
    public const DEFAULT_FOOTER = 'شكراً لزيارتك 🌷';

    public const SWITCHES = ['show_cashier', 'show_customer', 'show_serials'];

    /** The thermal printer's paper width (mm). */
    public const PAPERS = ['80', '58'];

    /** Product label rolls (mm, w × h) and the A4 sticker sheet; the first is the default. */
    public const PRODUCT_LABELS = ['38x25', '50x30', '58x40', 'a4'];

    /** Device sticker rolls (mm); the first is the default. */
    public const REPAIR_LABELS = ['58x40', '50x30', '60x50', '80x50'];

    /** @return array{tax_number: string|null, commercial_register: string|null, footer: string, show_cashier: bool, show_customer: bool, show_serials: bool, paper: string, product_label: string, repair_label: string} */
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
            'paper' => in_array($saved['paper'] ?? null, self::PAPERS, true) ? $saved['paper'] : '80',
            'product_label' => in_array($saved['product_label'] ?? null, self::PRODUCT_LABELS, true) ? $saved['product_label'] : self::PRODUCT_LABELS[0],
            'repair_label' => in_array($saved['repair_label'] ?? null, self::REPAIR_LABELS, true) ? $saved['repair_label'] : self::REPAIR_LABELS[0],
        ];
    }

    /** @param  array{tax_number?: string|null, commercial_register?: string|null, footer?: string|null, show_cashier?: bool, show_customer?: bool, show_serials?: bool, paper?: string, product_label?: string, repair_label?: string}  $values  a switch (or a paper / label size) left out keeps its value */
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
        $paper = (string) ($values['paper'] ?? '');
        $settings['receipt']['paper'] = in_array($paper, self::PAPERS, true) ? $paper : $current['paper'];
        foreach (['product_label' => self::PRODUCT_LABELS, 'repair_label' => self::REPAIR_LABELS] as $key => $sizes) {
            $size = (string) ($values[$key] ?? '');
            $settings['receipt'][$key] = in_array($size, $sizes, true) ? $size : $current[$key];
        }
        $tenant->settings = $settings;
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
