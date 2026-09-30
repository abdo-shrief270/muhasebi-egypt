<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Support\Modules\FeatureAccess;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

/**
 * The receipt behind the QR code / WhatsApp link: no login, found only by its random token,
 * and only what's printed on the paper receipt (no costs, no cashier details).
 */
final class PublicReceiptController
{
    public function show(string $token, ShopDirectory $shops, CurrentTenant $tenant, FeatureAccess $features): JsonResponse
    {
        $sale = Sale::withoutTenancy()->where('public_token', $token)->first();
        // The shop may have turned receipt links off.
        if ($sale === null || ! $features->enabled('sales.receipt_link', $sale->tenant_id)) {
            return response()->json(['message' => 'الإيصال ده مش موجود.', 'code' => 'receipt_not_found'], 404);
        }

        return $tenant->runAs($sale->tenant_id, function () use ($sale, $shops): JsonResponse {
            $sale->load(['items', 'payments']);
            $shop = $shops->find($sale->tenant_id);

            return response()->json(['data' => [
                'shop' => $shop ? ['name' => $shop->name, 'phone' => $shop->phone, 'receipt' => $shop->receipt] : null,
                'reference' => $sale->reference(),
                'status_label' => $sale->status->label(),
                'completed_at' => $sale->completed_at->toIso8601String(),
                'customer_name' => $sale->customer_name,
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'paid' => $sale->paid,
                'change' => $sale->change,
                'refunded' => $sale->refunded,
                'items' => $sale->items->map(fn (SaleItem $i): array => [
                    'name' => $i->name, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount' => $i->discount,
                    'line_total' => $i->line_total, 'returned_qty' => $i->returned_qty, 'serials' => $i->serials,
                ])->all(),
                'payments' => $sale->payments->map(fn (SalePayment $p): array => ['method_label' => $p->method->label(), 'amount' => $p->amount])->all(),
            ]]);
        });
    }
}
