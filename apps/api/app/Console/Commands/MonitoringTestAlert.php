<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Monitoring\Alerts;
use App\Support\Monitoring\Health;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:check {--test : send a test alert}')]
#[Description('Show the health checks; --test sends a test alert to the Telegram chat')]
final class MonitoringTestAlert extends Command
{
    public function handle(Health $health, Alerts $alerts): int
    {
        $result = $health->check();
        foreach ($result['checks'] as $name => $check) {
            $this->line(($check['ok'] ? '✓ ' : '✗ ')."{$name}: {$check['detail']}");
        }
        if ($this->option('test')) {
            if (! $alerts->configured()) {
                $this->error('TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID مش متحطين في .env');

                return self::FAILURE;
            }
            $this->info($alerts->send('✅ تنبيهات محاسبي شغالة.') ? 'Sent.' : 'Telegram refused it (check the token / chat id).');
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
