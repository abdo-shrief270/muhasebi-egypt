<?php

namespace Tests\Feature\Identity;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sign-up closed: the team opens shops from the admin panel. Meta hears of every sign-up and every
 * «كلّمنا واتساب» from the server too (Conversions API), the person matched by their phone, hashed.
 */
class OpenShopAndConversionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.meta' => ['pixel_id' => '123456', 'capi_token' => 'token', 'test_event_code' => '', 'graph_version' => 'v21.0']]);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    }

    /** @return list<array<string, mixed>> the events sent to Meta */
    private function metaEvents(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com/v21.0/123456/events'))
            ->map(fn ($pair) => $pair[0]->data()['data'][0])->values()->all();
    }

    public function test_an_admin_opens_a_shop_and_meta_hears_of_the_sign_up(): void
    {
        $admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@muhasebi.test', 'password' => 'secret-password']);
        Sanctum::actingAs($admin, ['admin']);
        $body = ['shop_name' => 'محل النور', 'owner_name' => 'أحمد', 'phone' => '01012345678', 'shop_types' => ['repair'], 'source' => 'facebook', 'campaign' => 'founders'];

        $opened = $this->postJson('/api/v1/admin/shops', $body)->assertCreated()->json('data');
        $this->assertSame('+201012345678', $opened['phone']);
        $this->assertMatchesRegularExpression('/^[A-Za-z2-9]{10}$/', $opened['password']);
        $this->assertStringEndsWith('/login', $opened['login_url']);
        $tenant = Tenant::query()->findOrFail($opened['tenant_id']);
        $this->assertSame(['source' => 'facebook', 'medium' => 'paid', 'campaign' => 'founders'], $tenant->settings['acquisition'] ?? null);
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'shop_created']);

        // Same phone again: refused. A type that isn't one: refused.
        $this->postJson('/api/v1/admin/shops', $body)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/admin/shops', [...$body, 'phone' => '01112345678', 'shop_types' => ['bakery']])->assertUnprocessable()->assertJsonValidationErrors('shop_types');

        [$event] = $this->metaEvents();
        $this->assertSame(['CompleteRegistration', "reg-{$opened['tenant_id']}", 'chat'], [$event['event_name'], $event['event_id'], $event['action_source']]);
        $this->assertSame([hash('sha256', '201012345678')], $event['user_data']['ph']);
        $this->assertArrayNotHasKey('client_ip_address', $event['user_data'], "never the admin's own IP");

        // The owner signs in with what the admin passes on.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => $opened['password'], 'device_name' => 'test'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_self_sign_up_and_a_whatsapp_contact_reach_meta_from_the_server(): void
    {
        $response = $this->withCredentials()->withUnencryptedCookies(['_fbp' => 'fb.1.1700000000000.123456789'])
            ->withHeader('Referer', 'https://app.muhasebi.test/register?utm_source=facebook')
            ->postJson('/api/v1/auth/register', [
                'shop_name' => 'محل', 'shop_types' => ['accessories'], 'owner_name' => 'أحمد', 'phone' => '01012345678',
                'password' => 'password123', 'password_confirmation' => 'password123',
            ])->assertCreated();
        $tenantId = Tenant::query()->value('id');
        $response->assertJsonPath('signup_event_id', "reg-{$tenantId}");

        $this->postJson('/api/v1/public/signup/contact', ['event_id' => 'bad id!'])->assertUnprocessable();
        $this->postJson('/api/v1/public/signup/contact', ['event_id' => 'contact-0123456789', 'url' => 'https://app.muhasebi.test/register'])->assertNoContent();

        [$signup, $contact] = $this->metaEvents();
        $this->assertSame(['CompleteRegistration', 'website', 'fb.1.1700000000000.123456789', 'https://app.muhasebi.test/register'], [$signup['event_name'], $signup['action_source'], $signup['user_data']['fbp'], $signup['event_source_url']]);
        $this->assertSame([hash('sha256', '201012345678')], $signup['user_data']['ph']);
        $this->assertSame(['Contact', 'contact-0123456789'], [$contact['event_name'], $contact['event_id']]);
        $this->assertArrayNotHasKey('ph', $contact['user_data']);
    }

    public function test_nothing_is_sent_without_a_token(): void
    {
        config(['services.meta.capi_token' => '']);
        $this->postJson('/api/v1/public/signup/contact', ['event_id' => 'contact-0123456789'])->assertNoContent();
        $this->assertSame([], $this->metaEvents());
    }
}
