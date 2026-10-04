<?php

namespace Tests\Feature\OnlineStore;

use App\Modules\Identity\PermissionResolver;
use App\Modules\Notifications\Models\Notification;
use App\Support\Events\EventRelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «احجز صيانة» on the store → the shop's list → a repair ticket. */
class RepairBookingsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.store.host' => '', 'services.store.url' => 'https://store.muhasebi.com']);
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', 'Africa/Cairo'));
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/online-store/settings', ['slug' => 'elnour', 'mode' => 'whatsapp', 'whatsapp' => '01011112222'])->assertOk();
    }

    private function book(array $overrides = [])
    {
        return $this->postJson('/api/v1/public/stores/elnour/repair-bookings', [
            'name' => 'منى', 'phone' => '01055556666', 'device' => 'iPhone 13', 'problem' => 'الشاشة مكسورة',
            'preferred_on' => '2026-10-12', 'consent' => true, ...$overrides,
        ]);
    }

    public function test_a_booking_from_the_store_becomes_a_ticket(): void
    {
        // Off until the shop switches it on.
        $this->assertNull($this->getJson('/api/v1/public/stores/elnour')->json('data.store.repair_booking'));
        $this->book()->assertStatus(409)->assertJsonPath('code', 'repair_booking_off');
        $settings = $this->putJson('/api/v1/online-store/settings', ['repair_booking' => true, 'repair_booking_note' => 'الكشف ببلاش'])->assertOk()->json('data');
        $this->assertSame([true, true], [$settings['repair_booking'], $settings['repairs_available']]);
        $this->assertSame(['note' => 'الكشف ببلاش'], $this->getJson('/api/v1/public/stores/elnour')->json('data.store.repair_booking'));

        // Booked (even in WhatsApp mode); the same form sent twice is saved once.
        $id = (string) Str::uuid7();
        $this->assertSame('BK-00001', $this->book(['id' => $id])->assertCreated()->json('data.reference'));
        $this->book(['id' => $id])->assertCreated();
        $this->book(['preferred_on' => '2026-10-01'])->assertUnprocessable()->assertJsonValidationErrors('preferred_on');
        $this->book(['website' => 'x'])->assertUnprocessable();
        app(EventRelay::class)->publishPending();
        $this->assertSame('حجز صيانة جديد BK-00001', Notification::withoutTenancy()->latest('created_at')->value('title'));

        $list = $this->getJson('/api/v1/online-store/bookings')->assertOk()->json();
        $this->assertSame([1, 1, 'iPhone 13', '+201055556666'], [$list['meta']['total'], $list['meta']['new'], $list['data'][0]['device'], $list['data'][0]['customer_phone']]);
        $this->postJson("/api/v1/online-store/bookings/{$id}/status", ['status' => 'contacted'])->assertOk()->assertJsonPath('data.status_label', 'اتكلمنا معاه');

        // The device arrives: the intake closes the booking.
        $ticket = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'منى', 'customer_phone' => '01055556666', 'device_name' => 'iPhone 13', 'reported_note' => 'الشاشة مكسورة', 'booking_id' => $id,
        ], ['X-Branch-Id' => $this->branchId])->assertCreated()->json('data');
        $booking = $this->getJson("/api/v1/online-store/bookings/{$id}")->json('data');
        $this->assertSame(['converted', $ticket['id'], $ticket['reference']], [$booking['status'], $booking['ticket_id'], $booking['ticket_reference']]);

        // Only once; and a converted booking can't be cancelled.
        $this->postJson('/api/v1/repairs/tickets', ['customer_name' => 'منى', 'customer_phone' => '01055556666', 'device_name' => 'iPhone 13', 'reported_note' => 'شاشة', 'booking_id' => $id], ['X-Branch-Id' => $this->branchId])
            ->assertUnprocessable()->assertJsonPath('code', 'booking_closed');
        $this->postJson("/api/v1/online-store/bookings/{$id}/status", ['status' => 'cancelled', 'reason' => 'x'])->assertUnprocessable()->assertJsonPath('code', 'booking_closed');
        $this->assertSame(1, $this->getJson('/api/v1/repairs/tickets', ['X-Branch-Id' => $this->branchId])->json('meta.total'));
    }

    public function test_limits_and_the_repairs_module(): void
    {
        $this->putJson('/api/v1/online-store/settings', ['repair_booking' => true])->assertOk();
        foreach (range(1, 3) as $i) {
            $this->book()->assertCreated();
        }
        $this->book()->assertStatus(429)->assertJsonPath('code', 'too_many_bookings');

        $first = $this->getJson('/api/v1/online-store/bookings')->json('data.0.id');
        $this->postJson("/api/v1/online-store/bookings/{$first}/status", ['status' => 'cancelled'])->assertUnprocessable()->assertJsonPath('code', 'cancel_reason_required');
        $this->postJson("/api/v1/online-store/bookings/{$first}/status", ['status' => 'cancelled', 'reason' => 'مش جاي'])->assertOk();
        $this->assertSame(1, $this->getJson('/api/v1/online-store/bookings?status=cancelled')->json('meta.total'));

        // Without the repairs module the store stops offering it.
        $this->postJson('/api/v1/modules/repairs/disable')->assertOk();
        $this->assertNull($this->getJson('/api/v1/public/stores/elnour')->json('data.store.repair_booking'));
        $this->book(['phone' => '01099998888'])->assertStatus(409)->assertJsonPath('code', 'repair_booking_off');
        $this->putJson('/api/v1/online-store/settings', ['repair_booking' => true])->assertUnprocessable()->assertJsonPath('code', 'repairs_not_enabled');
    }
}
