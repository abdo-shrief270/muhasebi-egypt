<?php

declare(strict_types=1);

namespace App\Modules\Messaging;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Messaging\Contracts\MessageHistory;
use App\Modules\Messaging\Listeners\AnonymiseCustomerMessages;
use App\Support\Modules\ModuleServiceProvider;

final class MessagingServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        CustomerErased::class => [AnonymiseCustomerMessages::class],
    ];

    public function register(): void
    {
        $this->app->bind(MessageHistory::class, MessageHistoryService::class);
    }
}
