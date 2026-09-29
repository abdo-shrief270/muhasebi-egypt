<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Sales\Contracts\CustomerSales;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Models\SaleReturn;
use Illuminate\Support\Carbon;

final class CustomerSalesService implements CustomerSales
{
    public function forCustomer(string $customerId): array
    {
        return Sale::query()
            ->where('customer_id', $customerId)
            ->with(['items', 'payments', 'returns'])
            ->orderBy('completed_at')
            ->get()
            ->map(fn (Sale $sale): array => [
                'reference' => $sale->reference(),
                'status' => $sale->status->label(),
                'completed_at' => $sale->completed_at->toIso8601String(),
                'customer_name' => $sale->customer_name,
                'customer_phone' => $sale->customer_phone,
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'paid' => $sale->paid,
                'credit' => $sale->credit,
                'refunded' => $sale->refunded,
                'items' => $sale->items->map(fn (SaleItem $i): array => [
                    'name' => $i->name,
                    'qty' => $i->qty,
                    'unit_price' => $i->unit_price,
                    'discount' => $i->discount,
                    'line_total' => $i->line_total,
                    'serials' => $i->serials,
                    'returned_qty' => $i->returned_qty,
                ])->all(),
                'payments' => $sale->payments->map(fn (SalePayment $p): array => [
                    'method' => $p->method->value,
                    'amount' => $p->amount,
                ])->all(),
                'returns' => $sale->returns->map(fn (SaleReturn $r): array => [
                    'reference' => $r->reference(),
                    'total' => $r->total,
                    'refund_method' => $r->refund_method->value,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    public function lastSaleAt(string $customerId): ?Carbon
    {
        $at = Sale::query()->where('customer_id', $customerId)->max('completed_at');

        return $at === null ? null : Carbon::parse((string) $at);
    }
}
