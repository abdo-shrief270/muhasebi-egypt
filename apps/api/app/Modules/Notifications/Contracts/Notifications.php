<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

/**
 * For other modules: a notification in the shop's bell, pushed to the devices of the users who
 * may see it (`permission`, null = everyone). Urgent ones also come during quiet hours.
 */
interface Notifications
{
    public function notify(string $type, string $title, ?string $body, string $icon, ?string $to, ?string $permission, bool $urgent = false): void;
}
