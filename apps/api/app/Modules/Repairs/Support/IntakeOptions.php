<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

/** The tick-boxes of the intake form. Stored by key, shown by label. */
final class IntakeOptions
{
    public const ACCESSORIES = [
        'case' => 'جراب',
        'sim' => 'شريحة',
        'memory' => 'كارت ميموري',
        'charger' => 'شاحن',
        'cable' => 'كابل',
        'box' => 'علبة',
        'pen' => 'قلم',
    ];

    public const CONDITION = [
        'scratches' => 'خدوش',
        'screen_cracked' => 'كسر في الشاشة',
        'back_cracked' => 'كسر في الضهر',
        'bent' => 'انحناء',
        'opened_before' => 'آثار فتح سابق',
        'water' => 'دخول مية',
    ];

    /** Asked before taking the device: protects the shop from "it worked when I left it". */
    public const CHECKS = [
        'powers_on' => 'بيفتح',
        'screen' => 'الشاشة شغالة',
        'touch' => 'التاتش شغال',
        'charging' => 'بيشحن',
        'cameras' => 'الكاميرات',
        'biometrics' => 'البصمة / Face ID',
        'network' => 'الشبكة',
    ];

    public const CHECK_VALUES = ['yes', 'no', 'unknown'];

    public const UNLOCK_TYPES = ['none', 'pin', 'pattern', 'password'];

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function toArray(): array
    {
        $list = fn (array $map) => array_map(fn (string $k, string $v) => ['value' => $k, 'label' => $v], array_keys($map), $map);

        return [
            'accessories' => $list(self::ACCESSORIES),
            'condition' => $list(self::CONDITION),
            'checks' => $list(self::CHECKS),
        ];
    }
}
