<?php

namespace Tests\Feature\Feedback;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Feedback\Http\Controllers\FeedbackController;
use App\Modules\Feedback\Models\ClientError;
use App\Modules\Feedback\Models\Feedback;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private function send(array $overrides = [])
    {
        return $this->post('/api/v1/feedback', [
            'type' => 'problem',
            'message' => 'زرار الطباعة مش شغال',
            'page' => '/sales/019b0000-0000-7000-8000-000000000000?q=أحمد',
            'app_version' => 'abc123',
            'screen' => '390x844',
            ...$overrides,
        ], ['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 Test']);
    }

    public function test_a_user_sends_feedback_with_its_context_and_a_screenshot(): void
    {
        Storage::fake('local');
        $owner = $this->actingAsOwnerOf();

        $this->send(['screenshot' => UploadedFile::fake()->image('shot.png', 800, 600)])->assertCreated();

        $row = app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => Feedback::query()->sole());
        $this->assertSame('problem', $row->type);
        $this->assertSame('المالك', $row->user_name);
        $this->assertSame('/sales/:id', $row->page); // no ids or search words kept
        $this->assertSame('390x844', $row->screen);
        $this->assertSame('Mozilla/5.0 Test', $row->user_agent);
        $this->assertSame('new', $row->status);
        $this->assertStringStartsWith("feedback/{$owner->tenant_id}/", (string) $row->screenshot_path);
        Storage::disk('local')->assertExists((string) $row->screenshot_path);
    }

    public function test_feedback_is_validated(): void
    {
        Storage::fake('local');
        $this->actingAsOwnerOf();

        $this->send(['type' => 'complaint'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->send(['message' => ''])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->send(['screen' => 'big'])->assertUnprocessable()->assertJsonValidationErrors('screen');
        $this->send(['screenshot' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('screenshot');
        $this->send(['screenshot' => UploadedFile::fake()->image('huge.png')->size(4000)])->assertUnprocessable()->assertJsonValidationErrors('screenshot');
    }

    public function test_feedback_is_rate_limited_per_user_and_per_shop(): void
    {
        $this->actingAsOwnerOf();
        for ($i = 0; $i < 5; $i++) {
            $this->send()->assertCreated();
        }
        $this->send()->assertTooManyRequests();

        // The shop's daily cap, whoever sends.
        $owner = $this->actingAsOwnerOf();
        app(CurrentTenant::class)->runAs($owner->tenant_id, function (): void {
            for ($i = 0; $i < FeedbackController::DAILY_LIMIT; $i++) {
                Feedback::create(['type' => 'question', 'message' => 'سؤال']);
            }
        });
        $this->send()->assertStatus(429)->assertJsonPath('code', 'feedback_limit');
    }

    public function test_client_errors_are_scrubbed_deduplicated_and_counted(): void
    {
        $owner = $this->actingAsOwnerOf();
        $error = [
            'kind' => 'error',
            'message' => "TypeError: can't read 'name' of customer 01012345678 (ahmed@example.com) 019b0000-0000-7000-8000-000000000001",
            'source' => 'https://app.muhasebi.test/_nuxt/entry.abc.js?v=2:1:2345',
            'stack' => 'at x (entry.js:1:2)',
            'page' => '/customers/019b0000-0000-7000-8000-000000000001?tab=sales',
        ];

        $this->postJson('/api/v1/client-errors', ['app_version' => 'abc', 'errors' => [$error]])->assertAccepted()->assertJsonPath('data.accepted', 1);
        // Same error for another customer: one row, counted.
        $other = [...$error, 'message' => "TypeError: can't read 'name' of customer 01198765432 (sara@example.com) 019b0000-0000-7000-8000-000000000002", 'count' => 3];
        $this->postJson('/api/v1/client-errors', ['errors' => [$other, ['kind' => 'rejection', 'message' => 'Failed to fetch']]])->assertAccepted();

        $rows = app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => ClientError::query()->orderBy('kind')->get());
        $this->assertCount(2, $rows);
        $this->assertSame(4, $rows[0]->count);
        $this->assertSame("TypeError: can't read 'name' of customer # ([email]) :id", $rows[0]->message);
        $this->assertSame('https://app.muhasebi.test/_nuxt/entry.abc.js:1:2345', $rows[0]->source);
        $this->assertSame('/customers/:id', $rows[0]->page);
        $this->assertSame($owner->id, $rows[0]->last_user_id);

        $this->postJson('/api/v1/client-errors', ['errors' => []])->assertUnprocessable();
    }

    public function test_client_errors_are_capped_and_throttled(): void
    {
        $owner = $this->actingAsOwnerOf();
        app(CurrentTenant::class)->runAs($owner->tenant_id, function (): void {
            for ($i = 0; $i < 200; $i++) {
                ClientError::create(['fingerprint' => sha1((string) $i), 'kind' => 'error', 'message' => "e{$i}", 'first_seen_at' => now(), 'last_seen_at' => now()]);
            }
        });
        $this->postJson('/api/v1/client-errors', ['errors' => [['kind' => 'error', 'message' => 'brand new']]])->assertAccepted()->assertJsonPath('data.accepted', 0);

        for ($i = 0; $i < 19; $i++) {
            $this->postJson('/api/v1/client-errors', ['errors' => [['kind' => 'error', 'message' => 'x']]])->assertAccepted();
        }
        $this->postJson('/api/v1/client-errors', ['errors' => [['kind' => 'error', 'message' => 'x']]])->assertTooManyRequests();
    }

    public function test_a_suspended_shop_can_still_send_feedback(): void
    {
        $owner = $this->actingAsOwnerOf();
        Subscription::withoutTenancy()->where('tenant_id', $owner->tenant_id)->update(['suspended_at' => now()]);
        cache()->flush();

        $this->send()->assertCreated();
        $this->postJson('/api/v1/client-errors', ['errors' => [['kind' => 'error', 'message' => 'x']]])->assertAccepted();
    }
}
