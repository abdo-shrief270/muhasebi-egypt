<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Product photos (three WebP widths, public) and the online-store switch per product. */
class ProductImagesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->openShopWithStock();
    }

    private function productId(): string
    {
        return $this->getJson('/api/v1/products')->assertOk()->json('data.0.id');
    }

    public function test_a_photo_is_kept_in_three_widths_and_served_publicly(): void
    {
        $id = $this->productId();
        $image = $this->post("/api/v1/products/{$id}/images", ['image' => UploadedFile::fake()->image('case.jpg', 2000, 1500)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data');
        $this->assertSame([2000, 1500], [$image['width'], $image['height']]);
        $this->assertSame(['320', '800', '1600'], array_map('strval', array_keys($image['urls'])));

        foreach ($image['urls'] as $width => $url) {
            $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
            $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
            $size = getimagesize($response->baseResponse->getFile()->getPathname());
            $this->assertSame((int) $width, $size[0], 'scaled to its width');
        }
        // Small pictures aren't blown up.
        $small = $this->post("/api/v1/products/{$id}/images", ['image' => UploadedFile::fake()->image('s.png', 500, 500)], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame(500, getimagesize($this->get($small['urls'][1600])->baseResponse->getFile()->getPathname())[0]);

        $this->assertSame([$image['id'], $small['id']], array_column($this->getJson("/api/v1/products/{$id}")->json('data.images'), 'id'));
        $this->putJson("/api/v1/products/{$id}/images/order", ['ids' => [$small['id'], $image['id']]])->assertOk();
        $this->assertSame([$small['id'], $image['id']], array_column($this->getJson("/api/v1/products/{$id}")->json('data.images'), 'id'));

        $this->deleteJson("/api/v1/products/{$id}/images/{$image['id']}")->assertNoContent();
        $this->get($image['urls'][320])->assertNotFound();
        $this->get('/api/v1/public/media/products/../../.env')->assertNotFound();
    }

    public function test_photos_need_products_manage_and_a_real_image(): void
    {
        $id = $this->productId();
        $this->post("/api/v1/products/{$id}/images", ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();

        Sanctum::actingAs($this->staff('cashier'));
        $this->post("/api/v1/products/{$id}/images", ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_products_can_be_hidden_from_the_online_store(): void
    {
        $id = $this->productId();
        $this->assertTrue($this->getJson("/api/v1/products/{$id}")->json('data.online_visible'));
        $this->patchJson('/api/v1/products/online', ['ids' => [$id], 'visible' => false])->assertOk()->assertJsonPath('data.updated', 1);
        $this->assertFalse($this->getJson("/api/v1/products/{$id}")->json('data.online_visible'));
        $this->patchJson("/api/v1/products/{$id}", ['online_visible' => true, 'online_description' => 'جراب سيليكون مقاوم للصدمات'])->assertOk()
            ->assertJsonPath('data.online_visible', true)->assertJsonPath('data.online_description', 'جراب سيليكون مقاوم للصدمات');
    }
}
