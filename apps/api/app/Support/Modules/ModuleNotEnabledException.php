<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Support\Exceptions\DomainRuleException;

final class ModuleNotEnabledException extends DomainRuleException
{
    public function __construct(ModuleManifest $module, ModuleState $state)
    {
        parent::__construct(
            message: "قسم «{$module->name}» غير مفعّل لهذا المحل.",
            errorCode: 'module_not_enabled',
            status: 403,
            context: ['module' => $module->key, 'state' => $state->value],
        );
    }
}
