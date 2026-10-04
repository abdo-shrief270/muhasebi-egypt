<?php

/*
 * Subscriptions. Prices in piasters, VAT (14%) included. A yearly subscription costs
 * `yearly_months` months. Payment is by InstaPay (the owner sends the transfer reference and
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

    'instapay' => [
        'address' => env('BILLING_INSTAPAY_ADDRESS', ''),
        'name' => env('BILLING_INSTAPAY_NAME', ''),
        'phone' => env('BILLING_INSTAPAY_PHONE', ''),
    ],

    'plans' => [
        'accessories' => [
            'name' => 'إكسسوارات',
            'description' => 'الأساس: الكاشير والمخزون والمشتريات والعملاء والخزنة والتقارير',
            'monthly' => 29900,
            'modules' => ['shop_orders'],
        ],
        'repair' => [
            'name' => 'صيانة',
            'description' => 'الأساس + الصيانة ومرتجعات الموردين والمستعمل',
            'monthly' => 44900,
            'modules' => ['repairs', 'supplier_returns', 'used_devices', 'shop_orders'],
        ],
        'pro' => [
            'name' => 'برو',
            'description' => 'صيانة + الشحن والتحويلات والاستيراد وتطبيق المالك',
            'monthly' => 54900,
            'modules' => ['repairs', 'supplier_returns', 'used_devices', 'services', 'imports', 'owner_app', 'shop_orders'],
            'featured' => true,
        ],
        'business' => [
            'name' => 'بيزنس',
            'description' => 'كل اللي في برو + الفروع المتعددة',
            'monthly' => 84900,
            'modules' => ['repairs', 'supplier_returns', 'used_devices', 'services', 'imports', 'owner_app', 'shop_orders', 'multi_branch'],
        ],
    ],

    // Optional modules bought on top of a plan (monthly).
    'modules' => [
        'repairs' => 14900,
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
