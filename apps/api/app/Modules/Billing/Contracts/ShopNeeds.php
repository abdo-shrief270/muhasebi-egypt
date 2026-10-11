<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/** What a shop says it does at registration («بتعمل إيه في المحل؟») and the modules that cover it. */
interface ShopNeeds
{
    /** @return list<string> the need keys there are */
    public function keys(): array;

    /**
     * @param  list<string>  $needs
     * @return list<string> the module keys that cover them
     */
    public function modulesFor(array $needs): array;
}
