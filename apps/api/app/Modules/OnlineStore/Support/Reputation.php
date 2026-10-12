<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Contracts\ShopReputation;
use Illuminate\Support\Facades\DB;

final class Reputation implements ShopReputation
{
    /** Below this many marketplace orders in the window, there's no response score yet. */
    public const MIN_ORDERS = 3;

    public const WINDOW_DAYS = 60;

    public function answerMinutes(): int
    {
        return max(5, (int) config('services.market.answer_minutes', 45));
    }

    public function scores(?array $tenantIds = null): array
    {
        if ($tenantIds === []) {
            return [];
        }
        $ratings = DB::table('online_reviews')->where('hidden', false)
            ->when($tenantIds !== null, fn ($q) => $q->whereIn('tenant_id', $tenantIds))
            ->groupBy('tenant_id')->selectRaw('tenant_id, avg(rating) as rating, count(*) as n')->get()->keyBy('tenant_id');

        $minutes = $this->answerMinutes();
        // Orders that had their chance: answered, cancelled, or older than the window to answer.
        $orders = DB::table('online_orders')->where('source', 'market')
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->where(fn ($q) => $q->where('status', '<>', 'new')->orWhere('created_at', '<', now()->subMinutes($minutes)))
            ->when($tenantIds !== null, fn ($q) => $q->whereIn('tenant_id', $tenantIds))
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, count(*) as n, count(*) filter (where answered_at is not null and answered_at <= created_at + make_interval(mins => ?)) as in_time', [$minutes])
            ->get()->keyBy('tenant_id');

        $out = [];
        foreach ($ratings->keys()->merge($orders->keys())->unique() as $tenantId) {
            $r = $ratings->get($tenantId);
            $o = $orders->get($tenantId);
            $out[(string) $tenantId] = [
                'rating' => $r !== null ? round((float) $r->rating, 1) : null,
                'reviews' => (int) ($r->n ?? 0),
                'response' => $o !== null && (int) $o->n >= self::MIN_ORDERS ? round((int) $o->in_time / (int) $o->n, 2) : null,
            ];
        }

        return $out;
    }

    public function latest(string $tenantId, int $limit = 10): array
    {
        return DB::table('online_reviews')->where('tenant_id', $tenantId)->where('hidden', false)
            ->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (object $r) => ['rating' => (int) $r->rating, 'comment' => $r->comment, 'name' => $r->customer_name, 'at' => (string) $r->created_at])
            ->all();
    }

    public function adminReviews(?bool $hidden = null, int $limit = 100): array
    {
        return DB::table('online_reviews as r')
            ->join('online_orders as o', 'o.id', '=', 'r.order_id')
            ->when($hidden !== null, fn ($q) => $q->where('r.hidden', $hidden))
            ->orderByDesc('r.created_at')->limit($limit)
            ->get(['r.id', 'r.tenant_id', 'o.number', 'r.rating', 'r.comment', 'r.customer_name', 'r.hidden', 'r.source', 'r.created_at'])
            ->map(fn (object $r) => [
                'id' => $r->id,
                'tenant_id' => $r->tenant_id,
                'order' => 'WEB-'.str_pad((string) $r->number, 5, '0', STR_PAD_LEFT),
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'name' => $r->customer_name,
                'hidden' => (bool) $r->hidden,
                'source' => $r->source,
                'at' => (string) $r->created_at,
            ])->all();
    }

    public function setHidden(string $reviewId, bool $hidden): ?string
    {
        $tenantId = DB::table('online_reviews')->where('id', $reviewId)->value('tenant_id');
        if ($tenantId !== null) {
            DB::table('online_reviews')->where('id', $reviewId)->update(['hidden' => $hidden, 'updated_at' => now()]);
        }

        return $tenantId;
    }
}
