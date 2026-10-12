<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /api/v1/diagnostics: misbehaving on purpose, only with the token. */
class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_off_without_a_token_and_with_a_wrong_one(): void
    {
        config(['services.diagnostics.token' => '']);
        $this->getJson('/api/v1/diagnostics/error')->assertNotFound();

        config(['services.diagnostics.token' => 'secret-token-123']);
        $this->getJson('/api/v1/diagnostics/error?token=nope')->assertNotFound();
        $this->getJson('/api/v1/diagnostics/hang')->assertNotFound();
    }

    public function test_an_error_a_slow_answer_and_no_answer(): void
    {
        config(['services.diagnostics.token' => 'secret-token-123']);

        $this->getJson('/api/v1/diagnostics/error?token=secret-token-123')->assertStatus(500)->assertJsonPath('code', 'diagnostics_error');
        $this->getJson('/api/v1/diagnostics/error?status=503', ['X-Diagnostics-Token' => 'secret-token-123'])->assertStatus(503);
        $this->getJson('/api/v1/diagnostics/error?status=200&token=secret-token-123')->assertStatus(500);

        $started = microtime(true);
        $this->getJson('/api/v1/diagnostics/slow?seconds=0.3&token=secret-token-123')->assertOk()->assertJsonPath('slept_seconds', 0.3);
        $this->assertGreaterThanOrEqual(0.3, microtime(true) - $started);

        $started = microtime(true);
        $this->getJson('/api/v1/diagnostics/hang?seconds=1&token=secret-token-123')->assertStatus(504);
        $this->assertGreaterThanOrEqual(1.0, microtime(true) - $started);
        // Errors on purpose aren't server errors: nothing for the Telegram alerts.
        $this->assertDatabaseCount('server_errors', 0);
    }
}
