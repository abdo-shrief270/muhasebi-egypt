<?php

/*
 * Subscriptions. Prices in piasters, VAT (14%) included; platform admins override them (billing_catalog).
 * A yearly subscription (when switched on) costs `yearly_months` months. Payment is by InstaPay (the owner sends the transfer reference and
 * a screenshot; a platform admin approves it) or activated by a platform admin directly.
 */
return [
    'trial_days' => 14,
    // After the paid period (or the trial) ends: everything works with a warning, then no new
    // products / users / branches, then read-only. Selling never stops before suspension.
    'grace_days' => 7,
    'suspend_after_days' => 60,

    'vat_basis_points' => 1400,
    'yearly_months' => 10,

    // The platform admin panel (/api/v1/admin/*): answered only on this host (e.g. admin.example.com)
    // and, when set, only from these IPs / CIDR ranges (comma separated). Empty = anywhere (dev, tests).
    'admin' => [
        'domain' => env('ADMIN_DOMAIN'),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', ''))))),
        'token_hours' => (int) env('ADMIN_TOKEN_HOURS', 8),
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
    ],

    // Rewards: points the shop earns, turned into credit off its next payment; the welcome a shop
    // invited by another gets (and the inviter's points come only once the new shop really pays).
    'rewards' => [
        'points_per_pound' => 10,
        'min_convert' => 100,
        'points' => [
            'referral' => 500,
            'early_renewal' => 50,
            'yearly' => 200,
            'onboarding' => 100,
        ],
        'referral_discount' => ['percent' => 20, 'months' => 3],
    ],

    // Partners (برنامج الشركاء): people who bring shops with their link earn rate_percent of what each
    // shop pays (without VAT or credit) for `months` from its first payment; a share is held
    // `hold_days` before it can be paid out, in requests of at least `min_payout` piasters.
    'affiliates' => [
        'rate_percent' => (float) env('AFFILIATE_RATE_PERCENT', 20),
        'months' => (int) env('AFFILIATE_MONTHS', 12),
        'hold_days' => (int) env('AFFILIATE_HOLD_DAYS', 14),
        'min_payout' => (int) env('AFFILIATE_MIN_PAYOUT', 20000),
        // A shop that registers with a partner's link gets the same welcome discount as an invited one.
        'welcome_discount' => true,
        'token_days' => 30,
        // Partners' links point at the website (it carries ?aff= to the sign-up page).
        'site_url' => rtrim((string) env('SITE_URL', 'https://muhasebi.com'), '/'),
    ],

    // «مدى الحياة»: a one-time price per plan (it covers the first year, payable in installments
    // each covering 12/N months), then a yearly maintenance fee. Only defaults: platform admins edit
    // it all (apps/admin «الأسعار»: billing_settings + billing_catalog). Off until they switch it on.
    // Extra modules without their own lifetime price cost `module_months` of their monthly price
    // for life and `module_maintenance_months` a year (rounded to 50 ج).
    'lifetime' => [
        'name' => 'مدى الحياة',
        'tagline' => 'اشتري السيستم مرة واحدة، وادفع صيانة ودعم بسيطة كل سنة.',
        'installments' => [1, 2, 3, 4],
        'perks' => [
            'السيستم بتاعك مدى الحياة بسعر ثابت',
            'التحديثات والمميزات الجديدة في باقتك من غير زيادة',
            'صيانة ودعم فني بمبلغ بسيط كل سنة',
            'تقدر تقسّط أول دفعة',
        ],
        'affiliate_rate_percent' => 10,
        'allow_coupons' => false,
        'module_months' => 17,
        'module_maintenance_months' => 4,
    ],

    // Yearly subscriptions (a year for `yearly_months` months): off unless an admin turns them on.
    'yearly_enabled' => false,

    'instapay' => [
        'address' => env('BILLING_INSTAPAY_ADDRESS', ''),
        'name' => env('BILLING_INSTAPAY_NAME', ''),
        'phone' => env('BILLING_INSTAPAY_PHONE', ''),
    ],

    'plans' => [
        'accessories' => [
            'name' => 'إكسسوارات',
            'description' => 'الأساس: الكاشير والمخزون والمشتريات والعملاء والخزنة والتقارير',
            'monthly' => 34900,
            'lifetime' => 600000,
            'maintenance' => 150000,
            'modules' => ['shop_orders'],
        ],
        'repair' => [
            'name' => 'صيانة',
            'description' => 'الأساس + الصيانة ومرتجعات الموردين والمستعمل',
            'monthly' => 49900,
            'lifetime' => 850000,
            'maintenance' => 180000,
            'modules' => ['repairs', 'supplier_returns', 'used_devices', 'shop_orders'],
        ],
        'pro' => [
            'name' => 'برو',
            'description' => 'صيانة + الشحن والتحويلات والاستيراد وتطبيق المالك',
            'monthly' => 64900,
            'lifetime' => 1050000,
            'maintenance' => 210000,
            'modules' => ['repairs', 'supplier_returns', 'used_devices', 'services', 'imports', 'owner_app', 'shop_orders'],
            'featured' => true,
        ],
        'business' => [
            'name' => 'بيزنس',
            'description' => 'كل اللي في برو + الفروع المتعددة',
            'monthly' => 94900,
            'lifetime' => 1600000,
            'maintenance' => 300000,
            'modules' => ['repairs', 'supplier_returns', 'used_devices', 'services', 'imports', 'owner_app', 'shop_orders', 'multi_branch'],
        ],
    ],

    // What a shop says it does when it registers («بتعمل إيه في المحل؟»): each need is covered by
    // modules; the shop's trial starts with them and the cheapest plan + extras that covers them is
    // recommended (`Support\Needs`). `types` = ticked in advance for those shop types.
    'needs' => [
        'repairs' => ['label' => 'صيانة واستلام أجهزة', 'description' => 'تذاكر، قطع غيار، ضمان، وتتبع الجهاز للعميل', 'icon' => 'i-lucide-wrench', 'modules' => ['repairs'], 'types' => ['repair']],
        'supplier_returns' => ['label' => 'مرتجعات للموردين', 'description' => 'القطع والبضاعة البايظة ترجع للمورد وتتحاسب', 'icon' => 'i-lucide-package-x', 'modules' => ['supplier_returns'], 'types' => ['repair']],
        'used_devices' => ['label' => 'شرا وبيع مستعمل', 'description' => 'تشتري موبايلات مستعملة بالبطاقة وتبيعها', 'icon' => 'i-lucide-smartphone', 'modules' => ['used_devices'], 'types' => ['phones']],
        'services' => ['label' => 'شحن رصيد وتحويلات', 'description' => 'فودافون كاش، إنستاباي، كروت الشحن', 'icon' => 'i-lucide-arrow-left-right', 'modules' => ['services'], 'types' => []],
        'installments' => ['label' => 'بيع بالتقسيط', 'description' => 'أقساط ومواعيدها وتحصيلها', 'icon' => 'i-lucide-calendar-clock', 'modules' => ['installments'], 'types' => ['phones']],
        'online_store' => ['label' => 'متجر أونلاين', 'description' => 'صفحة لمحلك أصنافها وأسعارها من المخزون', 'icon' => 'i-lucide-globe', 'modules' => ['online_store'], 'types' => []],
        'owner_app' => ['label' => 'أتابع المحل من موبايلي', 'description' => 'مبيعات اللحظة، تنبيهات، وموافقة على الخصومات', 'icon' => 'i-lucide-bell-ring', 'modules' => ['owner_app'], 'types' => []],
        'multi_branch' => ['label' => 'أكتر من فرع', 'description' => 'مخزون لكل فرع وتحويلات بينهم', 'icon' => 'i-lucide-building-2', 'modules' => ['multi_branch'], 'types' => []],
        'imports' => ['label' => 'استيراد من برّه', 'description' => 'شحنات، جمارك، وتكلفة وصول كل صنف', 'icon' => 'i-lucide-ship', 'modules' => ['imports'], 'types' => ['importer']],
        'shop_orders' => ['label' => 'شغل مع محلات تانية', 'description' => 'طلبيات من التجار وتصليح عند محل تاني', 'icon' => 'i-lucide-handshake', 'modules' => ['shop_orders'], 'types' => ['wholesale', 'repair']],
    ],

    // Optional modules bought on top of a plan (monthly).
    'modules' => [
        'repairs' => 19900,
        'imports' => 14900,
        'supplier_returns' => 7900,
        'used_devices' => 7900,
        'services' => 7900,
        'installments' => 7900,
        'owner_app' => 7900,
        'online_store' => 14900,
        'multi_branch' => 29900,
        'shop_orders' => 0,
    ],
];
