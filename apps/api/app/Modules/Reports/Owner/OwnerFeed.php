<?php

declare(strict_types=1);

namespace App\Modules\Reports\Owner;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * «اللي بيحصل»: what happened in the shop, newest first — sales, returns, expenses and money
 * taken out or put in the drawer, shifts opened and closed, selling-price changes.
 */
final class OwnerFeed
{
    public const KINDS = ['sale', 'return', 'cash', 'shift', 'price'];

    /**
     * @param  list<string>  $branchIds
     * @param  list<string>  $kinds
     * @return list<array<string, mixed>>
     */
    public function for(string $tenantId, array $branchIds, array $kinds, ?CarbonImmutable $before, int $limit = 40): array
    {
        $parts = [];
        $at = fn (Builder $q, string $column): Builder => $q->when($before, fn (Builder $w) => $w->where($column, '<', $before->utc()));

        if (in_array('sale', $kinds, true)) {
            $parts[] = $at(DB::table('sales')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds), 'completed_at')
                ->selectRaw("'sale' as kind, id::text as id, completed_at as at, branch_id, number, total as amount, discount as extra, cashier_name as user_name, customer_name as label, id::text as link_id");
        }
        if (in_array('return', $kinds, true)) {
            $parts[] = $at(DB::table('sale_returns')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds), 'created_at')
                ->selectRaw("'return' as kind, id::text as id, created_at as at, branch_id, number, total as amount, 0 as extra, created_by_name as user_name, reason as label, sale_id::text as link_id");
        }
        if (in_array('cash', $kinds, true)) {
            $parts[] = $at(DB::table('cash_movements')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds)->whereIn('type', ['expense', 'withdrawal', 'deposit']), 'created_at')
                ->selectRaw('type as kind, id::text as id, created_at as at, branch_id, 0 as number, amount, 0 as extra, user_name, note as label, shift_id::text as link_id');
        }
        if (in_array('shift', $kinds, true)) {
            $shifts = fn () => DB::table('cash_shifts')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds);
            $parts[] = $at($shifts(), 'opened_at')
                ->selectRaw("'shift_opened' as kind, id::text || '-o' as id, opened_at as at, branch_id, number, opening_cash as amount, 0 as extra, user_name, null as label, id::text as link_id");
            $parts[] = $at($shifts()->whereNotNull('closed_at'), 'closed_at')
                ->selectRaw("'shift_closed' as kind, id::text || '-c' as id, closed_at as at, branch_id, number, coalesce(cash_difference, 0) as amount, 0 as extra, user_name, closed_by_name as label, id::text as link_id");
        }
        if (in_array('price', $kinds, true)) {
            $parts[] = $at(DB::table('price_changes as c')->join('product_variants as v', 'v.id', '=', 'c.variant_id')->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('c.tenant_id', $tenantId)->where('c.field', 'price_retail'), 'c.created_at')
                ->selectRaw("'price' as kind, c.id::text as id, c.created_at as at, null as branch_id, 0 as number, c.new_price as amount, coalesce(c.old_price, 0) as extra, c.user_name, concat_ws(' ', p.name, v.name) as label, p.id::text as link_id");
        }
        if ($parts === []) {
            return [];
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }
        $branches = DB::table('branches')->where('tenant_id', $tenantId)->pluck('name', 'id');

        return DB::query()->fromSub($union, 'f')->orderByDesc('at')->limit($limit)->get()
            ->map(fn ($r) => $this->present($r, $branches->all()))->all();
    }

    /**
     * @param  array<string, string>  $branches
     * @return array<string, mixed>
     */
    private function present(object $r, array $branches): array
    {
        $money = fn (int $p) => number_format(abs($p) / 100, abs($p) % 100 ? 2 : 0).' ج';
        $number = (int) $r->number;
        $amount = (int) $r->amount;

        [$title, $icon, $tone, $to] = match ($r->kind) {
            'sale' => ['فاتورة INV-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT).($r->label ? " — {$r->label}" : ''), 'i-lucide-receipt', 'neutral', "/sales/{$r->link_id}"],
            'return' => ['مرتجع RET-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT).($r->label ? " — {$r->label}" : ''), 'i-lucide-undo-2', 'warning', "/sales/{$r->link_id}"],
            'expense' => ['مصروف'.($r->label ? " — {$r->label}" : ''), 'i-lucide-receipt-text', 'warning', $r->link_id ? "/cash/{$r->link_id}" : null],
            'withdrawal' => ['سحب من الدرج'.($r->label ? " — {$r->label}" : ''), 'i-lucide-arrow-up-from-line', 'warning', $r->link_id ? "/cash/{$r->link_id}" : null],
            'deposit' => ['إيداع في الدرج'.($r->label ? " — {$r->label}" : ''), 'i-lucide-arrow-down-to-line', 'neutral', $r->link_id ? "/cash/{$r->link_id}" : null],
            'shift_opened' => ['فتح وردية SH-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT).' بـ '.$money($amount), 'i-lucide-lock-open', 'neutral', "/cash/{$r->link_id}"],
            'shift_closed' => ['قفل وردية SH-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT).($amount === 0 ? ' والدرج مظبوط' : ($amount < 0 ? ' بعجز ' : ' بزيادة ').$money($amount)), 'i-lucide-lock', $amount === 0 ? 'success' : 'error', "/cash/{$r->link_id}"],
            'price' => ["سعر «{$r->label}» من ".$money((int) $r->extra).' لـ '.$money($amount), 'i-lucide-tag', 'neutral', "/products/{$r->link_id}"],
            default => [(string) $r->kind, 'i-lucide-circle', 'neutral', null],
        };

        return [
            'id' => $r->kind.':'.$r->id,
            'kind' => $r->kind,
            'at' => CarbonImmutable::parse($r->at)->toIso8601String(),
            'title' => $title,
            'icon' => $icon,
            'tone' => $tone,
            'amount' => match ($r->kind) {
                'sale' => $amount,
                'return', 'expense', 'withdrawal' => -abs($amount),
                'deposit' => abs($amount),
                default => null,
            },
            'discount' => $r->kind === 'sale' && (int) $r->extra > 0 ? (int) $r->extra : null,
            'user_name' => $r->user_name,
            'branch' => $r->branch_id ? ($branches[$r->branch_id] ?? null) : null,
            'to' => $to,
        ];
    }
}
