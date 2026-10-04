<?php

namespace Tests\Feature;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Support\Monitoring\Health;
use App\Support\Monitoring\ServerErrors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Server errors grouped + alerted, and the health endpoint. */
class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_errors_are_grouped_scrubbed_and_alerted_once_in_a_while(): void
    {
        config(['services.telegram.bot_token' => 'T', 'services.telegram.chat_id' => '42']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $boom = fn () => new \RuntimeException('boom for 01012345678 / ahmed@shop.com');
        foreach (range(1, 3) as $i) {
            app(ServerErrors::class)->capture($boom());
        }
        $row = DB::table('server_errors')->first();
        $this->assertSame([3, 'boom for [digits] / [email]'], [(int) $row->count, $row->message]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'خطأ في السيرفر') && $request['chat_id'] === '42');

        // The admins see it, resolve it; a new occurrence reopens it.
        $admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'a@muhasebi.test', 'password' => 'secret-password']);
        Sanctum::actingAs($admin, ['admin']);
        $errors = $this->getJson('/api/v1/admin/server-errors')->assertOk()->json();
        $this->assertSame([1, 3], [$errors['meta']['open'], $errors['data'][0]['count']]);
        $this->postJson("/api/v1/admin/server-errors/{$row->id}/resolve")->assertOk();
        $this->assertSame([], $this->getJson('/api/v1/admin/server-errors')->json('data'));
        app(ServerErrors::class)->capture($boom());
        $this->assertSame(1, $this->getJson('/api/v1/admin/server-errors')->json('meta.open'));
    }

    public function test_health_says_what_stopped(): void
    {
        config(['services.monitoring.min_free_percent' => 0]);
        $res = $this->getJson('/api/v1/health')->assertStatus(503)->json();
        $this->assertTrue($res['checks']['database']['ok']);
        $this->assertFalse($res['checks']['queue']['ok'], 'no heartbeat yet');

        Health::beat('queue');
        Health::beat('scheduler');
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('ok', true);

        $this->travel(10)->minutes();
        $this->assertStringContainsString('آخر نبض من 10 دقيقة', $this->getJson('/api/v1/health')->assertStatus(503)->json('checks.scheduler.detail'));
    }

    public function test_the_test_alert_says_why_telegram_refused_it(): void
    {
        config(['services.telegram.bot_token' => '123:abc', 'services.telegram.chat_id' => '42']);
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
            ->push(['ok' => true])]);
        $this->artisan('monitoring:check --test')
            ->expectsOutputToContain('Telegram refused it: Bad Request: chat not found')
            ->expectsOutputToContain('press Start')
            ->assertFailed();

        $this->artisan('monitoring:check --test')->expectsOutputToContain('Sent.')->assertSuccessful();
    }
}
