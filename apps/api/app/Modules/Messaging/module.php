<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'messaging',
    name: 'قوالب الرسائل',
    tier: ModuleTier::Core,
    description: 'قوالب WhatsApp وسجل الرسائل.',
    permissions: [
        'messages.send' => 'إرسال رسائل WhatsApp',
        'messages.templates' => 'تعديل قوالب الرسائل',
    ],
    menu: [
        new MenuItem('/settings/templates', 'قوالب الرسائل', 'i-lucide-message-circle', 'messages.templates', group: 'settings'),
    ],
    sort: 95,
);
