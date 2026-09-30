<?php

declare(strict_types=1);

namespace App\Modules\Feedback;

use App\Modules\Feedback\Contracts\FeedbackInbox;
use App\Modules\Feedback\Support\Inbox;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class FeedbackServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FeedbackInbox::class, Inbox::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Named limiters: their own counters per user, not shared with the other throttled routes.
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinutes(10, 5)->by('feedback:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('client-errors', fn (Request $request) => Limit::perMinute(20)->by('client-errors:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
