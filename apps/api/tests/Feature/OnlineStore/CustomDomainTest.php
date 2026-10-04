<?php

namespace Tests\Feature\OnlineStore;

use App\Modules\Identity\PermissionResolver;
use App\Modules\OnlineStore\Support\DomainDns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** A shop's own domain for its store: proved by TXT, then served (API host lookup, TLS check). */
class CustomDomainTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var array{txt: array<string, list<string>>, ips: array<string, list<string>>, cname: array<string, string>} */
    private array $dns = ['txt' => [], 'ips' => [], 'cname' => []];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.store.host' => 'muhasebi.com', 'services.store.url' => 'https://{slug}.muhasebi.com', 'app.url' => 'https://app.muhasebi.com']);
        $dns = &$this->dns;
        $this->app->instance(DomainDns::class, new class($dns) implements DomainDns
        {
            public function __construct(private array &$dns) {}

            public function txt(string $name): array
            {
                return $this->dns['txt'][$name] ?? [];
            }

            public function ips(string $name): array
            {
                return $this->dns['ips'][$name] ?? [];
            }

            public function cname(string $name): ?string
            {
                return $this->dns['cname'][$name] ?? null;
            }
        });
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/online-store/settings', ['slug' => 'elnour', 'mode' => 'whatsapp', 'whatsapp' => '01011112222'])->assertOk();
    }

    public function test_set_prove_and_serve_a_domain(): void
    {
        $this->putJson('/api/v1/online-store/domain', ['domain' => 'shop'])->assertUnprocessable()->assertJsonPath('code', 'domain_invalid');
        $this->putJson('/api/v1/online-store/domain', ['domain' => 'x.muhasebi.com'])->assertUnprocessable()->assertJsonPath('code', 'domain_platform');

        $set = $this->putJson('/api/v1/online-store/domain', ['domain' => 'https://WWW.Elnour-Mobile.com/'])->assertOk()->json('data');
        $this->assertSame(['www.elnour-mobile.com', false], [$set['domain'], $set['verified']]);
        [$txt, $cname] = $set['records'];
        $this->assertSame(['TXT', '_muhasebi.www.elnour-mobile.com'], [$txt['type'], $txt['name']]);
        $this->assertStringStartsWith('muhasebi-verify=', $txt['value']);
        $this->assertSame(['CNAME', 'elnour.muhasebi.com'], [$cname['type'], $cname['value']]);

        // Not proved yet: not served.
        $this->assertFalse($this->postJson('/api/v1/online-store/domain/verify')->assertOk()->json('data.verified'));
        $this->getJson('/api/v1/public/stores-tls?domain=www.elnour-mobile.com')->assertNotFound();

        // The TXT record proves it; the CNAME shows it reaches us.
        $this->dns['txt']['_muhasebi.www.elnour-mobile.com'] = [$txt['value']];
        $this->dns['cname']['www.elnour-mobile.com'] = 'elnour.muhasebi.com';
        $checked = $this->postJson('/api/v1/online-store/domain/verify')->assertOk()->json('data');
        $this->assertSame([true, true, 'https://www.elnour-mobile.com'], [$checked['verified'], $checked['points'], $checked['url']]);

        // Served: TLS, host lookup (both names lead to the shop's domain), the store's address.
        $this->getJson('/api/v1/public/stores-tls?domain=www.elnour-mobile.com')->assertOk();
        $this->assertSame(['slug' => 'elnour', 'domain' => 'www.elnour-mobile.com'], $this->getJson('/api/v1/public/stores-host?domain=www.elnour-mobile.com')->assertOk()->json('data'));
        $this->assertSame('www.elnour-mobile.com', $this->getJson('/api/v1/public/stores-host?domain=elnour.muhasebi.com')->json('data.domain'));
        $this->assertSame('https://www.elnour-mobile.com', $this->getJson('/api/v1/online-store/settings')->json('data.url'));
        $this->assertSame('www.elnour-mobile.com', $this->getJson('/api/v1/public/stores/elnour')->json('data.store.domain'));
        $this->artisan('online-store:domains')->expectsOutput('www.elnour-mobile.com')->assertSuccessful();

        // A closed store's domain isn't served.
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'off'])->assertOk();
        $this->getJson('/api/v1/public/stores-tls?domain=www.elnour-mobile.com')->assertNotFound();
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'whatsapp'])->assertOk();

        // Changed: proved again before it's served. Removed: gone at once.
        $this->putJson('/api/v1/online-store/domain', ['domain' => 'elnour.shop'])->assertOk()->assertJsonPath('data.verified', false);
        $this->getJson('/api/v1/public/stores-tls?domain=www.elnour-mobile.com')->assertNotFound();
        $this->deleteJson('/api/v1/online-store/domain')->assertOk()->assertJsonPath('data.domain', null);
        $this->assertSame('https://elnour.muhasebi.com', $this->getJson('/api/v1/online-store/settings')->json('data.url'));
    }

    public function test_a_domain_points_to_us_by_address_and_belongs_to_one_shop(): void
    {
        $set = $this->putJson('/api/v1/online-store/domain', ['domain' => 'elnour-mobile.com'])->assertOk()->json('data');
        $this->dns['txt']['_muhasebi.elnour-mobile.com'] = ['other', $set['records'][0]['value']];
        $this->dns['ips']['elnour.muhasebi.com'] = ['203.0.113.7'];
        $this->dns['ips']['elnour-mobile.com'] = ['198.51.100.1'];
        $this->assertSame([true, false], [$this->postJson('/api/v1/online-store/domain/verify')->json('data.verified'), $this->postJson('/api/v1/online-store/domain/verify')->json('data.points')]);
        $this->dns['ips']['elnour-mobile.com'] = ['203.0.113.7'];
        $this->assertTrue($this->postJson('/api/v1/online-store/domain/verify')->json('data.points'));

        // Another shop can't take it.
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/online-store/settings', ['slug' => 'other-shop', 'mode' => 'whatsapp', 'whatsapp' => '01011113333'])->assertOk();
        $this->putJson('/api/v1/online-store/domain', ['domain' => 'elnour-mobile.com'])->assertUnprocessable()->assertJsonPath('code', 'domain_taken');
    }
}
