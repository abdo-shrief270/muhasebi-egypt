<?php

use Illuminate\Support\Facades\Route;

// The Android app (Google Play, a Trusted Web Activity): proves this site and the app belong
// together, so the app opens without the browser bar. Empty until TWA_PACKAGE / TWA_SHA256 are set.
Route::get('/.well-known/assetlinks.json', function () {
    $package = (string) config('services.twa.package');
    $fingerprints = config('services.twa.fingerprints');

    return response()->json($package === '' || $fingerprints === [] ? [] : [[
        'relation' => ['delegate_permission/common.handle_all_urls'],
        'target' => ['namespace' => 'android_app', 'package_name' => $package, 'sha256_cert_fingerprints' => $fingerprints],
    ]])->header('Cache-Control', 'public, max-age=3600');
});

Route::get('/', fn () => response()->json(['name' => config('app.name'), 'api' => url('/api/v1'), 'docs' => url('/docs/api')]));
