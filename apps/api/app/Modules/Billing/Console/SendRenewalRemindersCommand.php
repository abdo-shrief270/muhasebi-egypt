<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Support\RenewalReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:remind')]
#[Description('Remind shop owners that their subscription or trial is ending (once per step)')]
final class SendRenewalRemindersCommand extends Command
{
    public function handle(RenewalReminders $reminders): int
    {
        $this->info($reminders->send().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
