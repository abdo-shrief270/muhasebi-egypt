<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Where new shops come from: the campaign kept at sign-up, and the admins' count by source. */
class AcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $phone, ?array $acquisition): void
    {
        $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل '.$phone, 'shop_types' => ['accessories'], 'owner_name' => 'سامي', 'phone' => $phone,
            'password' => 'password123', 'password_confirmation' => 'password123',
            ...($acquisition === null ? [] : ['acquisition' => $acquisition]),
        ])->assertCreated();
    }

    public function test_the_sign_up_campaign_is_kept_and_counted_for_the_admins(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل', 'shop_types' => ['accessories'], 'owner_name' => 'سامي', 'phone' => '01099990009',
            'password' => 'password123', 'password_confirmation' => 'password123', 'acquisition' => ['gclid' => 'x'],
        ])->assertUnprocessable()->assertJsonValidationErrors('acquisition');

        $this->register('01099990001', ['source' => 'facebook', 'medium' => 'paid', 'campaign' => 'launch']);
        $this->register('01099990002', ['source' => 'facebook', 'medium' => 'paid', 'campaign' => 'launch']);
        $this->register('01099990003', ['source' => 'google', 'medium' => 'organic']);
        $this->register('01099990004', null);

        $first = Tenant::query()->where('name', 'محل 01099990001')->firstOrFail();
        $this->assertSame(['source' => 'facebook', 'medium' => 'paid', 'campaign' => 'launch'], $first->settings['acquisition']);

        Sanctum::actingAs(PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'a@muhasebi.test', 'password' => 'secret-password']), ['admin']);
        $this->postJson("/api/v1/admin/shops/{$first->id}/activate", ['plan' => 'accessories', 'cycle' => 'monthly'])->assertOk();

        $rows = collect($this->getJson('/api/v1/admin/acquisition?days=30')->assertOk()->json('data.rows'))->keyBy('source');
        $this->assertSame([2, 1, 'launch'], [$rows['facebook']['shops'], $rows['facebook']['paying'], $rows['facebook']['campaign']]);
        $this->assertSame([1, 0], [$rows['google']['shops'], $rows['google']['paying']]);
        $this->assertSame(1, $rows['direct']['shops']);
        $this->assertSame('facebook', $this->getJson("/api/v1/admin/shops/{$first->id}")->json('data.shop.acquisition.source'));
    }
}
