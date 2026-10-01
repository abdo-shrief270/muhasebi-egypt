<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * Roles every new shop starts with. Owners can edit them or add their own.
 */
final class DefaultRoles
{
    /**
     * @param  list<string>  $allPermissions
     * @return array<string, array{name: string, permissions: list<string>}>
     */
    public static function all(array $allPermissions): array
    {
        return [
            'manager' => [
                'name' => 'مدير فرع',
                'permissions' => array_values(array_diff($allPermissions, ['roles.manage', 'audit.view'])),
            ],
            'cashier' => [
                'name' => 'كاشير',
                'permissions' => [
                    'sales.sell', 'sales.view', 'products.view', 'inventory.view',
                    'customers.view', 'customers.manage', 'cash.shift', 'cash.expenses', 'messages.send',
                    'repairs.view', 'repairs.create', 'repairs.deliver', 'services.manage', 'shop_orders.view', 'installments.collect',
                ],
            ],
            'technician' => [
                'name' => 'فني صيانة',
                'permissions' => [
                    'repairs.view', 'repairs.create', 'repairs.update_status', 'products.view',
                    'inventory.view', 'messages.send', 'shop_orders.view', 'shop_orders.fulfil',
                ],
            ],
            'storekeeper' => [
                'name' => 'أمين مخزن',
                'permissions' => [
                    'products.view', 'products.manage', 'products.view_cost', 'inventory.view', 'inventory.adjust',
                    'suppliers.view', 'suppliers.manage', 'supplier_returns.view', 'supplier_returns.manage',
                    'imports.view', 'shop_orders.view', 'shop_orders.place', 'shop_orders.fulfil',
                ],
            ],
        ];
    }
}
