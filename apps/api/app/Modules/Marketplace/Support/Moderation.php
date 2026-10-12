<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\MarketModeration;
use App\Modules\Marketplace\Jobs\SyncShopVariants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Moderation implements MarketModeration
{
    public function report(string $offerId, ?array $offer, string $reason, ?string $note, ?string $contact, string $ip): bool
    {
        $ipHash = hash_hmac('sha256', $ip, (string) config('app.key'));
        $again = DB::table('market_reports')->where('offer_id', $offerId)->where('ip_hash', $ipHash)
            ->where('created_at', '>', now()->subDay())->exists();
        if ($again) {
            return false;
        }
        DB::table('market_reports')->insert([
            'id' => (string) Str::uuid7(),
            'offer_id' => $offerId,
            'tenant_id' => $offer['tenant_id'] ?? null,
            'variant_id' => $offer['variant_id'] ?? null,
            'title' => isset($offer['title']) ? mb_substr((string) $offer['title'], 0, 200) : null,
            'reason' => $reason,
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'contact' => $contact !== null && trim($contact) !== '' ? mb_substr(trim($contact), 0, 30) : null,
            'ip_hash' => $ipHash,
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function reports(?string $status = 'new', int $limit = 200): array
    {
        return DB::table('market_reports')->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (object $r) => [
                'id' => $r->id,
                'offer_id' => $r->offer_id,
                'tenant_id' => $r->tenant_id,
                'variant_id' => $r->variant_id,
                'title' => $r->title,
                'reason' => $r->reason,
                'reason_label' => self::REASONS[$r->reason] ?? $r->reason,
                'note' => $r->note,
                'contact' => $r->contact,
                'status' => $r->status,
                'resolution' => $r->resolution,
                'resolved_by_name' => $r->resolved_by_name,
                'resolved_at' => $r->resolved_at,
                'blocked' => $r->tenant_id !== null && $r->variant_id !== null
                    && DB::table('market_blocks')->where('tenant_id', $r->tenant_id)->where('variant_id', $r->variant_id)->exists(),
                // How many people reported the same offer.
                'count' => DB::table('market_reports')->where('offer_id', $r->offer_id)->count(),
                'at' => (string) $r->created_at,
            ])->all();
    }

    public function openCount(): int
    {
        return DB::table('market_reports')->where('status', 'new')->count();
    }

    public function resolve(string $reportId, string $action, ?string $by): ?array
    {
        $report = DB::table('market_reports')->where('id', $reportId)->first();
        if ($report === null) {
            return null;
        }
        DB::transaction(function () use ($report, $action, $by): void {
            $close = ['status' => 'resolved', 'resolution' => $action === 'hide' ? 'hidden' : 'dismissed', 'resolved_by_name' => $by, 'resolved_at' => now(), 'updated_at' => now()];
            if ($action === 'hide' && $report->tenant_id !== null && $report->variant_id !== null) {
                DB::table('market_blocks')->insertOrIgnore([
                    'tenant_id' => $report->tenant_id, 'variant_id' => $report->variant_id,
                    'reason' => self::REASONS[$report->reason] ?? $report->reason, 'created_at' => now(), 'updated_at' => now(),
                ]);
                // Every open report on the same offer is settled by hiding it.
                DB::table('market_reports')->where('offer_id', $report->offer_id)->where('status', 'new')->update($close);
            } else {
                DB::table('market_reports')->where('id', $report->id)->update($close);
            }
        });
        if ($action === 'hide' && $report->tenant_id !== null && $report->variant_id !== null) {
            SyncShopVariants::dispatch($report->tenant_id, [$report->variant_id]);
        }

        return ['tenant_id' => $report->tenant_id, 'variant_id' => $report->variant_id];
    }

    public function unblock(string $tenantId, string $variantId): void
    {
        DB::table('market_blocks')->where('tenant_id', $tenantId)->where('variant_id', $variantId)->delete();
        SyncShopVariants::dispatch($tenantId, [$variantId]);
    }
}
