<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Marketplace\Contracts\MarketModeration;
use App\Modules\OnlineStore\Contracts\ShopReputation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «بلاغات السوق والتقييمات»: what visitors reported in «سوق محاسبي», and the shops' reviews. */
final class AdminMarketController
{
    public function __construct(
        private readonly MarketModeration $moderation,
        private readonly ShopReputation $reputation,
        private readonly ShopDirectory $shops,
        private readonly AdminLog $log,
    ) {}

    public function reports(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['new', 'resolved', 'all'])]])['status'] ?? 'new';

        return response()->json(['data' => $this->withShops($this->moderation->reports($status === 'all' ? null : $status))]);
    }

    public function resolve(Request $request, string $report): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['dismiss', 'hide'])]]);
        $done = $this->moderation->resolve($report, $data['action'], $request->user()?->getAttribute('name'));
        abort_if($done === null, 404);
        $this->log->record($data['action'] === 'hide' ? 'market_offer_hidden' : 'market_report_dismissed', $done['tenant_id'], $report, ['variant_id' => $done['variant_id']]);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function unblock(Request $request): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid'], 'variant_id' => ['required', 'uuid']]);
        $this->moderation->unblock($data['tenant_id'], $data['variant_id']);
        $this->log->record('market_offer_unhidden', $data['tenant_id'], $data['variant_id']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $hidden = $request->validate(['hidden' => ['nullable', 'boolean']])['hidden'] ?? null;

        return response()->json(['data' => $this->withShops($this->reputation->adminReviews($hidden === null ? null : (bool) $hidden))]);
    }

    public function review(Request $request, string $review): JsonResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        $tenantId = $this->reputation->setHidden($review, (bool) $data['hidden']);
        abort_if($tenantId === null, 404);
        $this->log->record($data['hidden'] ? 'review_hidden' : 'review_shown', $tenantId, $review);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withShops(array $rows): array
    {
        $names = [];
        foreach ($rows as &$row) {
            $id = $row['tenant_id'] ?? null;
            if ($id !== null && ! array_key_exists($id, $names)) {
                $names[$id] = $this->shops->find($id)?->name;
            }
            $row['shop'] = $id !== null ? $names[$id] : null;
        }

        return $rows;
    }
}
