<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

/** Which messages were opened for whom, as other modules see it. */
interface MessageHistory
{
    /**
     * @param  list<string>  $subjectIds
     * @return array<string, string> subject id => ISO time of the last message with that template
     */
    public function lastSent(string $subjectType, array $subjectIds, string $template): array;
}
