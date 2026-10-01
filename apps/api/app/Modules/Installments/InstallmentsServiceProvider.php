<?php

declare(strict_types=1);

namespace App\Modules\Installments;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Customers\Events\CustomerPaid;
use App\Modules\Installments\Listeners\AnonymiseInstallmentCustomers;
use App\Modules\Installments\Listeners\ApplyAccountPayments;
use App\Support\Modules\ModuleServiceProvider;

final class InstallmentsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        CustomerPaid::class => [ApplyAccountPayments::class],
        CustomerErased::class => [AnonymiseInstallmentCustomers::class],
    ];
}
