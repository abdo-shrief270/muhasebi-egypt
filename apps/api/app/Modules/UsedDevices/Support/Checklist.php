<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

/**
 * The quick inspection when buying: each check is yes / no / na (couldn't check). The phone's
 * account (iCloud / Google) must be signed out, or the next owner can't use it — and a phone
 * still locked to someone else's account is the classic stolen phone.
 */
final class Checklist
{
    public const REQUIRED_YES = 'account_removed';

    /** key => label */
    public const ITEMS = [
        'account_removed' => 'iCloud / حساب جوجل متشال',
        'screen' => 'الشاشة سليمة (من غير كسر ولا بقع)',
        'touch' => 'التاتش شغال في كل الشاشة',
        'body' => 'الجسم والضهر من غير كسر',
        'biometrics' => 'Face ID / البصمة شغالة',
        'cameras' => 'الكاميرات شغالة',
        'audio' => 'السماعة والمايك شغالين',
        'charging' => 'الشحن شغال',
        'network' => 'الشبكة والشريحة شغالين',
        'wifi' => 'الواي فاي والبلوتوث',
        'buttons' => 'الزراير شغالة',
        'original_parts' => 'مفيش قطع متغيرة',
        'box' => 'معاه العلبة',
        'charger' => 'معاه الشاحن',
    ];

    public const VALUES = ['yes', 'no', 'na'];

    /**
     * @return list<array{key: string, label: string, required: bool}>
     */
    public static function items(): array
    {
        return array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'required' => $key === self::REQUIRED_YES], array_keys(self::ITEMS), self::ITEMS);
    }

    /**
     * Every known check, unanswered ones as na.
     *
     * @param  array<string, string>  $given
     * @return array<string, string>
     */
    public static function complete(array $given): array
    {
        $out = [];
        foreach (array_keys(self::ITEMS) as $key) {
            $out[$key] = in_array($given[$key] ?? null, self::VALUES, true) ? $given[$key] : 'na';
        }

        return $out;
    }
}
