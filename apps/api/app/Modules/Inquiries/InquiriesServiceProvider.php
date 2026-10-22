<?php

declare(strict_types=1);

namespace App\Modules\Inquiries;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Inquiries\Console\RemindInquiriesCommand;
use App\Modules\Inquiries\Listeners\AnonymiseCustomerInquiries;
use App\Support\Modules\ModuleServiceProvider;

final class InquiriesServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        CustomerErased::class => [AnonymiseCustomerInquiries::class],
    ];

    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([RemindInquiriesCommand::class]);
        }
    }
}
