<?php

declare(strict_types=1);

namespace App\Modules\Messaging;

use App\Modules\Messaging\Contracts\MessageHistory;
use App\Support\Modules\ModuleServiceProvider;

final class MessagingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MessageHistory::class, MessageHistoryService::class);
    }
}
