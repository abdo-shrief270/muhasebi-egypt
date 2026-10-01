<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('notifications:vapid-keys')]
#[Description('Print a new VAPID key pair for push notifications (put them in .env once; changing them unsubscribes every device)')]
final class GenerateVapidKeysCommand extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
