<?php

namespace Tests\Feature\Identity;

use Tests\TestCase;

/** The Google Play app's link to the site (Trusted Web Activity). */
class AssetLinksTest extends TestCase
{
    public function test_asset_links_follow_the_server_settings(): void
    {
        config(['services.twa.package' => '', 'services.twa.fingerprints' => []]);
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

        config(['services.twa.package' => 'com.muhasebi.app', 'services.twa.fingerprints' => ['AB:CD']]);
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => 'com.muhasebi.app', 'sha256_cert_fingerprints' => ['AB:CD']],
        ]]);
    }
}
