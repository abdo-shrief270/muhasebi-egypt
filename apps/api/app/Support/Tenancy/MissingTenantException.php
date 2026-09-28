<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use LogicException;

final class MissingTenantException extends LogicException
{
    public function __construct()
    {
        parent::__construct('No tenant is set for the current context.');
    }
}
