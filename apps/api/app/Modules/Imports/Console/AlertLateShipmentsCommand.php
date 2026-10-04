<?php

declare(strict_types=1);

namespace App\Modules\Imports\Console;

use App\Modules\Imports\Support\LateShipments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('imports:late-alerts')]
#[Description('Tell importers about shipments past their expected arrival (once per expected date)')]
final class AlertLateShipmentsCommand extends Command
{
    public function handle(LateShipments $late): int
    {
        $this->info($late->alert().' alert(s) sent.');

        return self::SUCCESS;
    }
}
