<?php

namespace Tests\Feature\Events;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\Repairs\Listeners\SeedDefaultFaults;
use App\Modules\Repairs\Models\FaultCategory;
use App\Support\Events\EventRecorder;
use App\Support\Events\EventRelay;
use App\Support\Events\StoredEvent;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_an_event_recorded_in_a_rolled_back_transaction_never_exists(): void
    {
        $owner = $this->registerShop();
        $before = StoredEvent::query()->count();

        try {
            DB::transaction(function () use ($owner): void {
                app(EventRecorder::class)->record(new ModuleEnabled($owner->tenant_id, 'imports', 'trial'));
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame($before, StoredEvent::query()->count());
    }

    public function test_events_are_published_once_and_marked(): void
    {
        Event::fake([ModuleEnabled::class]);
        $owner = $this->registerShop(ShopType::Repair);
        StoredEvent::query()->update(['published_at' => null]);

        $published = app(EventRelay::class)->publishPending();

        $this->assertGreaterThan(0, $published);
        $this->assertSame(0, StoredEvent::query()->whereNull('published_at')->count());
        Event::assertDispatched(ModuleEnabled::class, fn (ModuleEnabled $e): bool => $e->tenantId === $owner->tenant_id && $e->eventId() !== null);

        $this->assertSame(0, app(EventRelay::class)->publishPending(), 'nothing left to publish');
    }

    public function test_a_listener_reacts_only_once_to_the_same_event(): void
    {
        $owner = $this->registerShop(ShopType::Repair);
        $stored = StoredEvent::query()->where('name', ModuleEnabled::NAME)->where('payload->moduleKey', 'repairs')->firstOrFail();
        $count = fn (): int => app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => FaultCategory::query()->count());

        app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => FaultCategory::query()->delete());
        $this->assertSame(0, $count());

        // Redelivering the already-processed event is a no-op.
        app(SeedDefaultFaults::class)->handle($stored->toDomainEvent());
        $this->assertSame(0, $count());
        $this->assertSame(1, DB::table('processed_events')->where('event_id', $stored->id)->count());
    }

    public function test_listeners_skip_shops_that_do_not_have_their_module(): void
    {
        $owner = $this->registerShop(ShopType::Accessories);

        $event = (new ModuleEnabled($owner->tenant_id, 'repairs', 'trial'))->withEventId((string) str()->uuid());
        app(SeedDefaultFaults::class)->handle($event);

        $this->assertSame(0, FaultCategory::withoutTenancy()->where('tenant_id', $owner->tenant_id)->count());
        $this->assertDatabaseMissing('processed_events', ['event_id' => $event->eventId()]);
    }
}
