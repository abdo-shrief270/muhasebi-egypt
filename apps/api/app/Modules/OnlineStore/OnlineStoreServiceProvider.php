<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\OnlineStore\Actions\RepairBookingActions;
use App\Modules\OnlineStore\Contracts\OnlineOrders;
use App\Modules\OnlineStore\Contracts\RepairBookings;
use App\Modules\OnlineStore\Listeners\AnonymiseCustomerOrders;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class OnlineStoreServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        CustomerErased::class => [AnonymiseCustomerOrders::class],
    ];

    public function register(): void
    {
        $this->app->bind(OnlineOrders::class, OnlineOrdersService::class);
        $this->app->bind(RepairBookings::class, RepairBookingActions::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The store's server forwards each customer's IP; this only stops floods of the read-only API.
        RateLimiter::for('store', fn (Request $request) => Limit::perMinute(300)->by('store:'.$request->ip()));
        // Placing orders: a few per customer, so nobody floods the shop with fake ones.
        RateLimiter::for('store-orders', fn (Request $request) => Limit::perMinutes(10, 20)->by('store-orders:'.$request->ip()));
        // Guessing codes: a few tries per client.
        RateLimiter::for('store-coupons', fn (Request $request) => Limit::perMinutes(10, 15)->by('store-coupons:'.$request->ip()));
    }
}
