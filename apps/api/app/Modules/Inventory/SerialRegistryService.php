<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Inventory\Models\SerialEvent;
use App\Modules\Inventory\Models\SerialNumber;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Collection;

final class SerialRegistryService implements SerialRegistry
{
    public function __construct(
        private readonly Auth $auth,
        private readonly CurrentTenant $tenant,
    ) {}

    public function receive(string $branchId, string $variantId, array $serials, StockReference $reference): void
    {
        $serials = $this->distinct($serials);
        $existing = $this->rows($serials);

        foreach ($serials as $serial) {
            $row = $existing->get($serial);
            if ($row?->status === SerialNumber::IN_STOCK) {
                throw new DomainRuleException("السيريال {$serial} موجود في المخزن فعلاً.", 'serial_in_stock', context: ['serial' => $serial]);
            }
            $row ??= new SerialNumber(['tenant_id' => $this->tenant->idOrFail(), 'serial' => $serial]);
            $row->fill(['variant_id' => $variantId, 'branch_id' => $branchId, 'status' => SerialNumber::IN_STOCK])->save();
            $this->event($row, $branchId, $reference);
        }
    }

    public function issue(string $branchId, string $variantId, array $serials, StockReference $reference): void
    {
        $serials = $this->distinct($serials);
        $existing = $this->rows($serials);

        foreach ($serials as $serial) {
            $row = $existing->get($serial);
            if ($row === null) {
                // Never recorded coming in (opening stock, stock from before serials were tracked): recorded as it leaves.
                $row = SerialNumber::create(['tenant_id' => $this->tenant->idOrFail(), 'serial' => $serial, 'variant_id' => $variantId, 'branch_id' => $branchId, 'status' => SerialNumber::OUT]);
                $this->event($row, $branchId, $reference);

                continue;
            }
            if ($row->status !== SerialNumber::IN_STOCK || $row->branch_id !== $branchId || $row->variant_id !== $variantId) {
                throw new DomainRuleException(
                    $row->status === SerialNumber::IN_STOCK ? "السيريال {$serial} مسجّل على صنف أو فرع تاني." : "السيريال {$serial} خرج قبل كده.",
                    'serial_not_in_stock',
                    context: ['serial' => $serial],
                );
            }
            $row->update(['status' => SerialNumber::OUT]);
            $this->event($row, $branchId, $reference);
        }
    }

    public function takeBack(string $branchId, string $variantId, array $serials, StockReference $reference, bool $restock): void
    {
        $serials = $this->distinct($serials);
        $existing = $this->rows($serials);

        foreach ($serials as $serial) {
            $row = $existing->get($serial);
            if ($row === null || $row->status !== SerialNumber::OUT || $row->variant_id !== $variantId) {
                throw new DomainRuleException("السيريال {$serial} مش من القطع اللي خرجت.", 'serial_not_sold', context: ['serial' => $serial]);
            }
            $row->update(['status' => $restock ? SerialNumber::IN_STOCK : SerialNumber::DAMAGED, 'branch_id' => $branchId]);
            $this->event($row, $branchId, $reference);
        }
    }

    public function setAside(string $branchId, string $variantId, array $serials, StockReference $reference): void
    {
        $serials = $this->distinct($serials);
        $existing = $this->rows($serials);

        foreach ($serials as $serial) {
            $row = $existing->get($serial);
            if ($row === null) {
                $row = SerialNumber::create(['tenant_id' => $this->tenant->idOrFail(), 'serial' => $serial, 'variant_id' => $variantId, 'branch_id' => $branchId, 'status' => SerialNumber::DAMAGED]);
                $this->event($row, $branchId, $reference);

                continue;
            }
            if ($row->status !== SerialNumber::IN_STOCK || $row->branch_id !== $branchId || $row->variant_id !== $variantId) {
                throw new DomainRuleException("السيريال {$serial} مش في مخزن الفرع ده على الصنف ده.", 'serial_not_in_stock', context: ['serial' => $serial]);
            }
            $row->update(['status' => SerialNumber::DAMAGED]);
            $this->event($row, $branchId, $reference);
        }
    }

    public function putBack(string $branchId, string $variantId, array $serials, StockReference $reference): void
    {
        $this->fromDamaged($branchId, $variantId, $serials, $reference, SerialNumber::IN_STOCK);
    }

    public function release(string $branchId, string $variantId, array $serials, StockReference $reference): void
    {
        $this->fromDamaged($branchId, $variantId, $serials, $reference, SerialNumber::OUT);
    }

    public function origins(array $serials): array
    {
        if ($serials === []) {
            return [];
        }

        $incoming = [MovementType::Purchase->value, MovementType::Opening->value, MovementType::SupplierReplacement->value];
        $found = [];
        SerialEvent::query()
            ->join('serial_numbers', 'serial_numbers.id', '=', 'serial_events.serial_id')
            ->whereIn('serial_numbers.serial', $serials)
            ->whereIn('serial_events.type', $incoming)
            ->orderBy('serial_events.seq')
            ->get(['serial_numbers.serial', 'serial_events.type', 'serial_events.ref_type', 'serial_events.ref_id'])
            ->each(function (SerialEvent $e) use (&$found): void {
                // Ordered oldest first: the latest arrival wins.
                $found[(string) $e->getAttribute('serial')] = [
                    'type' => $e->type->value,
                    'ref_type' => $e->ref_type,
                    'ref_id' => $e->ref_id,
                ];
            });

        return $found;
    }

    public function inStock(string $branchId, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $found = [];
        SerialNumber::query()
            ->where('branch_id', $branchId)
            ->where('status', SerialNumber::IN_STOCK)
            ->whereIn('variant_id', $variantIds)
            ->orderBy('serial')
            ->get(['variant_id', 'serial'])
            ->each(function (SerialNumber $row) use (&$found): void {
                $found[$row->variant_id][] = $row->serial;
            });

        return $found;
    }

    public function history(string $serial): ?array
    {
        $row = SerialNumber::query()->where('serial', $this->normalize([$serial])[0])->with('events')->first();

        return $row === null ? null : [
            'serial' => $row->serial,
            'status' => $row->status,
            'variant_id' => $row->variant_id,
            'branch_id' => $row->branch_id,
            'events' => $row->events->map(fn (SerialEvent $e): array => [
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'ref_type' => $e->ref_type,
                'ref_id' => $e->ref_id,
                'note' => $e->note,
                'user_name' => $e->user_name,
                'created_at' => $e->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function normalize(array $serials): array
    {
        return array_values(array_map(fn (string $s): string => strtoupper((string) preg_replace('/[\s\-\/]+/u', '', $s)), $serials));
    }

    /**
     * @param  list<string>  $serials
     */
    private function fromDamaged(string $branchId, string $variantId, array $serials, StockReference $reference, string $to): void
    {
        $serials = $this->distinct($serials);
        $existing = $this->rows($serials);

        foreach ($serials as $serial) {
            $row = $existing->get($serial);
            if ($row === null || $row->status !== SerialNumber::DAMAGED || $row->variant_id !== $variantId) {
                throw new DomainRuleException("السيريال {$serial} مش من القطع التالفة / المرتجعة.", 'serial_not_set_aside', context: ['serial' => $serial]);
            }
            $row->update(['status' => $to, 'branch_id' => $branchId]);
            $this->event($row, $branchId, $reference);
        }
    }

    /**
     * @param  list<string>  $serials
     * @return list<string>
     */
    private function distinct(array $serials): array
    {
        $normalized = $this->normalize($serials);
        if (count($normalized) !== count(array_unique($normalized))) {
            throw new DomainRuleException('فيه سيريال متكرر.', 'serial_duplicate');
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $serials
     * @return Collection<string, SerialNumber>
     */
    private function rows(array $serials): Collection
    {
        return SerialNumber::query()->whereIn('serial', $serials)->lockForUpdate()->get()->keyBy('serial');
    }

    private function event(SerialNumber $row, string $branchId, StockReference $reference): void
    {
        SerialEvent::create([
            'tenant_id' => $row->getAttribute('tenant_id'),
            'serial_id' => $row->id,
            'branch_id' => $branchId,
            'type' => $reference->type,
            'ref_type' => $reference->refType,
            'ref_id' => $reference->refId,
            'note' => $reference->note,
            'user_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
