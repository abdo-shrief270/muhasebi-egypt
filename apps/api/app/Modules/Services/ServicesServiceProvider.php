<?php

declare(strict_types=1);

namespace App\Modules\Services;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Services\Contracts\ServiceProfits;
use App\Modules\Services\Listeners\AnonymiseCustomerServices;
use App\Support\Modules\ModuleServiceProvider;

final class ServicesServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        CustomerErased::class => [AnonymiseCustomerServices::class],
    ];

    public function register(): void
    {
        $this->app->bind(ServiceProfits::class, ServiceProfitsService::class);
    }
}
