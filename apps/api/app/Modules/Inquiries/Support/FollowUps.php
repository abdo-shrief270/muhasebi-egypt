<?php

declare(strict_types=1);

namespace App\Modules\Inquiries\Support;

use App\Modules\Inquiries\Models\Inquiry;
use App\Modules\Notifications\Contracts\Notifications;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

/** The bell (and a push) when an open inquiry's call-back time comes: once per time set. */
final class FollowUps
{
    public function __construct(
        private readonly Notifications $notifications,
        private readonly CurrentTenant $tenant,
    ) {}

    /** @return int how many reminders went out */
    public function remind(): int
    {
        $sent = 0;
        Inquiry::withoutTenancy()->where('status', 'open')->whereNull('reminded_at')->where('follow_up_at', '<=', now())
            ->orderBy('follow_up_at')
            ->each(function (Inquiry $i) use (&$sent): void {
                // Claimed first, so two runs never remind twice.
                if (DB::table('inquiries')->where('id', $i->id)->whereNull('reminded_at')->update(['reminded_at' => now()]) !== 1) {
                    return;
                }
                $this->tenant->runAs($i->tenant_id, fn () => $this->notifications->notify(
                    'inquiry.follow_up',
                    "كلّم {$i->customer_name}",
                    mb_strimwidth($i->text, 0, 120, '…'),
                    'i-lucide-phone-call',
                    '/inquiries?status=due',
                    'inquiries.view',
                ));
                $sent++;
            });

        return $sent;
    }
}
