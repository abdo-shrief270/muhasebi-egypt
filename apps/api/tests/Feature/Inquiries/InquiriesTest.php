<?php

namespace Tests\Feature\Inquiries;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class InquiriesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
    }

    public function test_an_inquiry_records_the_customer_and_is_followed_up(): void
    {
        Sanctum::actingAs($this->staff('cashier'));
        $inquiry = $this->postJson('/api/v1/inquiries', [
            'customer_name' => 'يوسف عادل', 'customer_phone' => '01098765432', 'consent' => true,
            'kind' => 'item', 'text' => 'جراب iPhone 16 Pro شفاف — عايز 2', 'follow_up_at' => now()->addDays(2)->toIso8601String(),
        ])->assertCreated()->json('data');
        $this->assertSame(['يوسف عادل', 'open', 'صنف مش موجود'], [$inquiry['customer_name'], $inquiry['status'], $inquiry['kind_label']]);
        // He's a real customer now.
        $this->assertDatabaseHas('customers', ['id' => $inquiry['customer_id'], 'name' => 'يوسف عادل']);

        // A second one for the same customer, then the customer's list.
        $this->postJson('/api/v1/inquiries', ['customer_id' => $inquiry['customer_id'], 'kind' => 'repair', 'text' => 'تغيير ضهر S23 بكام؟'])->assertCreated();
        $this->assertCount(2, $this->getJson("/api/v1/inquiries?customer_id={$inquiry['customer_id']}")->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/inquiries?q=iPhone')->json('data'));

        // Its call-back time comes: one reminder, then it's due.
        DB::table('inquiries')->where('id', $inquiry['id'])->update(['follow_up_at' => now()->subMinute()]);
        $this->artisan('inquiries:remind')->expectsOutput('1 reminder(s) sent.')->assertSuccessful();
        $this->artisan('inquiries:remind')->expectsOutput('0 reminder(s) sent.')->assertSuccessful();
        $this->assertDatabaseHas('notifications', ['type' => 'inquiry.follow_up']);
        $this->assertSame(1, $this->getJson('/api/v1/inquiries')->json('meta.counts.due'));

        // Turned into a sale.
        $this->patchJson("/api/v1/inquiries/{$inquiry['id']}", ['status' => 'converted', 'converted_to' => 'sale', 'outcome' => 'اشترى 2'])->assertOk()->assertJsonPath('data.status', 'converted');
        $this->assertSame(1, $this->getJson('/api/v1/inquiries')->json('meta.counts.converted'));
    }

    public function test_erasing_the_customer_clears_their_inquiries(): void
    {
        $inquiry = $this->postJson('/api/v1/inquiries', ['customer_name' => 'سارة', 'customer_phone' => '01011112222', 'kind' => 'phone', 'text' => 'Redmi Note 14 متاح؟'])->assertCreated()->json('data');
        $this->postJson("/api/v1/customers/{$inquiry['customer_id']}/erase")->assertOk();
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry['id'], 'customer_phone' => null, 'text' => '—']);
    }

    public function test_permissions(): void
    {
        Sanctum::actingAs($this->staff('storekeeper'));
        $this->getJson('/api/v1/inquiries')->assertForbidden();
        $this->postJson('/api/v1/inquiries', ['customer_name' => 'x', 'customer_phone' => '01011112222', 'kind' => 'item', 'text' => 'x'])->assertForbidden();
    }
}
