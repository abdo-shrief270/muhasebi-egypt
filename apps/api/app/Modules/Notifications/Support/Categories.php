<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * The kinds of notification a user can mute for push (the bell always shows them). A
 * notification's category comes from its type; `permission` = who could receive it at all.
 */
final class Categories
{
    /** @var array<string, array{label: string, description: string, permission: string, prefixes: list<string>, urgent?: bool}> */
    public const ALL = [
        'partners' => [
            'label' => 'المحلات الشريكة',
            'description' => 'طلبات جديدة، طلبات شراكة، والمحل التاني بيحرّك طلب.',
            'permission' => 'shop_orders.view',
            'prefixes' => ['shop_order.', 'shop_connection.'],
        ],
        'cash' => [
            'label' => 'فرق في الدرج',
            'description' => 'وردية اتقفلت بعجز أو زيادة أكبر من الحد اللي في «المميزات».',
            'permission' => 'owner_app.alerts',
            'prefixes' => ['owner.cash_difference'],
        ],
        'returns' => [
            'label' => 'المرتجعات',
            'description' => 'كل مرتجع مبيعات بقيمته ومين عمله.',
            'permission' => 'owner_app.alerts',
            'prefixes' => ['owner.refund'],
        ],
        'summary' => [
            'label' => 'ملخص آخر اليوم',
            'description' => 'المبيعات والمكسب والمصروفات والمرتجع في رسالة واحدة آخر اليوم.',
            'permission' => 'owner_app.alerts',
            'prefixes' => ['owner.daily_summary'],
        ],
    ];

    public static function of(string $type): ?string
    {
        foreach (self::ALL as $key => $category) {
            foreach ($category['prefixes'] as $prefix) {
                if (str_starts_with($type, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
