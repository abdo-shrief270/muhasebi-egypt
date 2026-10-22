<?php

declare(strict_types=1);

namespace App\Modules\Inquiries\Console;

use App\Modules\Inquiries\Support\FollowUps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inquiries:remind')]
#[Description('Remind the shop to call back customers whose inquiry follow-up time has come')]
final class RemindInquiriesCommand extends Command
{
    public function handle(FollowUps $followUps): int
    {
        $this->info($followUps->remind().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
