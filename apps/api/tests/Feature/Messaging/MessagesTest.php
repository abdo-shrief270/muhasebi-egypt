<?php

namespace Tests\Feature\Messaging;

use App\Modules\Identity\Models\User;
use App\Modules\Messaging\Models\MessageLog;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift(10000);
    }

    private function template(string $key): array
    {
        return collect($this->getJson('/api/v1/messages/templates')->assertOk()->json('data'))->firstWhere('key', $key);
    }

    public function test_the_shop_rewords_a_template_and_can_go_back_to_the_built_in_one(): void
    {
        $ready = $this->template('repair_ready');
        $this->assertFalse($ready['customized']);
        $this->assertStringContainsString('{device}', $ready['body']);
        $this->assertContains('due', array_column($ready['variables'], 'name'));

        $this->putJson('/api/v1/messages/templates/repair_ready', ['body' => "يا {customer}، {device} خلص 🎉\n{link}"])->assertOk();
        $ready = $this->template('repair_ready');
        $this->assertSame([true, "يا {customer}، {device} خلص 🎉\n{link}"], [$ready['customized'], $ready['body']]);
        $this->assertTrue(AuditEntry::query()->where('action', 'messages.template_updated')->exists());

        $this->deleteJson('/api/v1/messages/templates/repair_ready')->assertOk();
        $this->assertSame($ready['default_body'], $this->template('repair_ready')['body']);

        $this->putJson('/api/v1/messages/templates/nope_nope', ['body' => 'x'])->assertNotFound();
        $this->putJson('/api/v1/messages/templates/repair_ready', ['body' => ''])->assertUnprocessable();
    }

    public function test_a_ready_device_is_flagged_until_the_customer_is_told(): void
    {
        $ticket = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'محمود', 'customer_phone' => '01234567890', 'device_name' => 'iPhone 11', 'reported_note' => 'مش بيشحن',
        ])->assertCreated()->json('data');
        $url = "/api/v1/repairs/tickets/{$ticket['id']}";
        foreach (['diagnosing', 'repairing', 'ready'] as $status) {
            $this->postJson("{$url}/status", ['status' => $status])->assertOk();
        }

        $this->assertSame(1, $this->getJson('/api/v1/repairs/summary')->json('data.unnotified'));
        $this->assertNull($this->getJson($url)->json('data.ready_notified_at'));

        // A message sent earlier (while repairing) doesn't count.
        $this->postJson('/api/v1/messages/log', ['template' => 'repair_repairing', 'subject_type' => 'repair_ticket', 'subject_id' => $ticket['id'], 'phone' => '+201234567890'])->assertCreated();
        $this->assertSame(1, $this->getJson('/api/v1/repairs/summary')->json('data.unnotified'));

        $this->postJson('/api/v1/messages/log', ['template' => 'repair_ready', 'subject_type' => 'repair_ticket', 'subject_id' => $ticket['id'], 'phone' => '+201234567890'])->assertCreated();
        $this->assertSame(0, $this->getJson('/api/v1/repairs/summary')->json('data.unnotified'));
        $this->assertNotNull($this->getJson($url)->json('data.ready_notified_at'));
        $this->assertNotNull($this->getJson('/api/v1/repairs/tickets?status=ready')->json('data.0.ready_notified_at'));

        $history = $this->getJson('/api/v1/messages/log?'.http_build_query(['subject_type' => 'repair_ticket', 'subject_id' => $ticket['id']]))->assertOk()->json('data');
        $this->assertSame(['الجهاز جاهز', 'جاري الإصلاح'], array_column($history, 'label'));

        $this->expectException(LogicException::class);
        $this->inShop(fn () => MessageLog::query()->first()->delete());
    }

    public function test_who_may_do_what(): void
    {
        $this->postJson('/api/v1/messages/log', ['template' => 'nope'])->assertUnprocessable();

        // A cashier sends messages and reads the wording, but doesn't edit it.
        Sanctum::actingAs($cashier = $this->staff('cashier'));
        $this->getJson('/api/v1/messages/templates')->assertOk();
        $this->postJson('/api/v1/messages/log', ['template' => 'debt_reminder'])->assertCreated();
        $this->putJson('/api/v1/messages/templates/debt_reminder', ['body' => 'x'])->assertForbidden();
        $this->assertInstanceOf(User::class, $cashier);
    }
}
