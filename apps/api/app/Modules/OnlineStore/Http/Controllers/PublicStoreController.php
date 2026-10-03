<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\Catalog\Contracts\StorefrontQuery;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Support\Storefront;
use App\Modules\OnlineStore\Support\StoreMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The store's public, read-only API ({slug}.muhasebi.com): what the customer may see —
 * names, online prices, photos, availability. Never costs, barcodes, suppliers or stock counts
 * (unless the owner shows them).
 */
final class PublicStoreController
{
    public function __construct(private readonly Storefront $storefront) {}

    public function show(Request $request): JsonResponse
    {
        return $this->cached(['data' => $this->storefront->home($this->store($request))]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'model' => ['nullable', 'integer'],
            'quality' => ['nullable', 'string', 'in:original,service_pack,high_copy,copy'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:'.implode(',', StorefrontQuery::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $query = new StorefrontQuery(
            q: $data['q'] ?? null,
            categoryId: isset($data['category']) ? (int) $data['category'] : null,
            brandId: isset($data['brand']) ? (int) $data['brand'] : null,
            deviceModelId: isset($data['model']) ? (int) $data['model'] : null,
            quality: $data['quality'] ?? null,
            minPrice: isset($data['min']) ? (int) $data['min'] : null,
            maxPrice: isset($data['max']) ? (int) $data['max'] : null,
            sort: $data['sort'] ?? 'new',
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? 24),
        );
        $page = $this->storefront->products($this->store($request), $query);

        return $this->cached(['data' => $page['items'], 'meta' => ['total' => $page['total'], 'page' => $query->page, 'per_page' => $query->perPage]]);
    }

    public function product(Request $request, string $slug, string $product): JsonResponse
    {
        $data = $this->storefront->product($this->store($request), $product);
        if ($data === null) {
            return response()->json(['message' => 'الصنف ده مش موجود في المتجر.', 'code' => 'product_not_found'], 404);
        }

        return $this->cached(['data' => $data]);
    }

    /** Every product on the store (sitemap). */
    public function index(Request $request): JsonResponse
    {
        return $this->cached(['data' => $this->storefront->index($this->store($request))]);
    }

    /** The Meta / Google product feed's rows (the store renders the XML). */
    public function feed(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->storefront->feed($this->store($request))])
            ->header('Cache-Control', 'public, max-age=900');
    }

    /**
     * Caddy asks before getting an HTTPS certificate for a subdomain (on-demand TLS): yes only for
     * an open store's ({slug}.muhasebi.com), so nobody can make it fetch certificates for anything.
     */
    public function tlsCheck(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    /** Logo / cover, public and cached for a year (a new upload gets a new name). */
    public function media(string $path): BinaryFileResponse
    {
        $file = StoreMedia::file('stores/'.$path);
        abort_if($file === null, 404);

        return response()->file($file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function store(Request $request): OnlineStore
    {
        return $request->attributes->get('online_store');
    }

    /** Short shared caching: the store's SSR caches too, and stock moves all day. */
    private function cached(array $body): JsonResponse
    {
        return response()->json($body)->header('Cache-Control', 'public, max-age=30');
    }
}
