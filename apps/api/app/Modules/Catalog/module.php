<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'catalog',
    name: 'الأصناف',
    tier: ModuleTier::Core,
    description: 'التصنيفات والماركات والموديلات والأصناف والتوافق.',
    permissions: [
        'products.view' => 'عرض الأصناف',
        'products.manage' => 'إضافة وتعديل الأصناف والأسعار',
        'products.view_cost' => 'رؤية سعر التكلفة',
    ],
    menu: [
        new MenuItem('/products', 'الأصناف', 'i-lucide-package', 'products.view', group: 'stock'),
        new MenuItem('/products/prices', 'تعديل الأسعار', 'i-lucide-tags', 'products.manage', group: 'stock', feature: 'catalog.bulk_prices'),
    ],
    provider: CatalogServiceProvider::class,
    features: [
        new Feature('catalog.excel_import', 'استيراد الأصناف من إكسل', 'رفع ملف إكسل فيه أصناف كتير مرة واحدة (بالكميات الافتتاحية).'),
        new Feature('catalog.bulk_prices', 'تعديل الأسعار بالجملة', 'شاشة «تعديل الأسعار»: تزوّد أو تخفّض أسعار أصناف كتير مرة واحدة. لو قفلتها الأسعار تتعدل من الصنف نفسه بس.'),
        new Feature('catalog.labels', 'طباعة ليبلات الباركود', 'شاشة «ليبلات باركود» وأزرار «اطبع ليبل». لو قفلتها الباركود فاضل على الأصناف بس من غير طباعة.'),
    ],
    featuresIntro: 'الأصناف والأسعار والباركود.',
    sort: 20,
);
