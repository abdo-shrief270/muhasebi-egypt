<?php

use App\Modules\SupplierReturns\SupplierReturnsServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'supplier_returns',
    name: 'مرتجعات الموردين',
    tier: ModuleTier::Optional,
    description: 'سلة المرتجعات والتالف متفرزة حسب المصدر تلقائي، وإذن مرتجع لكل مورد برسالة واتساب جاهزة.',
    dependsOn: ['inventory', 'suppliers'],
    permissions: [
        'supplier_returns.view' => 'عرض مرتجعات الموردين',
        'supplier_returns.manage' => 'فرز وإرسال المرتجعات',
    ],
    menu: [
        new MenuItem('/supplier-returns', 'مرتجعات الموردين', 'i-lucide-undo-2', 'supplier_returns.view', group: 'stock'),
    ],
    provider: SupplierReturnsServiceProvider::class,
    shopTypes: ['repair', 'wholesale', 'importer'],
    sort: 220,
);
