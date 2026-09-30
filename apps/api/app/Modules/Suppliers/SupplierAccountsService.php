<?php

declare(strict_types=1);

namespace App\Modules\Suppliers;

use App\Modules\Suppliers\Contracts\SupplierAccounts;
use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Modules\Suppliers\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Support\SupplierAccount;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SupplierAccountsService implements SupplierAccounts
{
    public function __construct(private readonly SupplierAccount $account) {}

    public function find(array $supplierIds): array
    {
        if ($supplierIds === []) {
            return [];
        }

        return Supplier::query()
            ->whereIn('id', array_values(array_unique($supplierIds)))
            ->get(['id', 'name', 'phone'])
            ->mapWithKeys(fn (Supplier $s): array => [$s->id => ['id' => $s->id, 'name' => $s->name, 'phone' => $s->phone]])
            ->all();
    }

    public function active(): array
    {
        return Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Supplier $s): array => ['id' => $s->id, 'name' => $s->name, 'phone' => $s->phone])
            ->values()
            ->all();
    }

    public function purchases(array $purchaseIds): array
    {
        if ($purchaseIds === []) {
            return [];
        }

        return Purchase::query()
            ->with('supplier:id,name')
            ->whereIn('id', array_values(array_unique($purchaseIds)))
            ->get()
            ->mapWithKeys(fn (Purchase $p): array => [$p->id => [
                'supplier_id' => $p->supplier_id,
                'supplier_name' => (string) $p->supplier?->name,
                'reference' => $p->reference(),
            ]])
            ->all();
    }

    public function recentPurchasesOf(string $variantId, int $limit = 5): array
    {
        // The newest purchase line of the variant from each supplier (the tenant scope comes with Purchase).
        $latest = Purchase::query()->toBase()
            ->join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.variant_id', $variantId)
            ->selectRaw('distinct on (purchases.supplier_id) purchases.supplier_id, purchases.id as purchase_id, purchases.number, purchases.invoice_date, purchase_items.net_unit_cost, purchase_items.lot_id')
            ->orderBy('purchases.supplier_id')
            ->orderByDesc('purchases.invoice_date')
            ->orderByDesc('purchases.number');

        return DB::query()->fromSub($latest, 'l')
            ->join('suppliers', 'suppliers.id', '=', 'l.supplier_id')
            ->orderByDesc('l.invoice_date')
            ->orderByDesc('l.number')
            ->limit($limit)
            ->get(['l.*', 'suppliers.name as supplier_name'])
            ->map(fn (object $r): array => [
                'supplier_id' => (string) $r->supplier_id,
                'supplier_name' => (string) $r->supplier_name,
                'purchase_id' => (string) $r->purchase_id,
                'reference' => 'PUR-'.str_pad((string) $r->number, 5, '0', STR_PAD_LEFT),
                'date' => substr((string) $r->invoice_date, 0, 10),
                'unit_cost' => (int) $r->net_unit_cost,
                'lot_id' => $r->lot_id !== null ? (string) $r->lot_id : null,
            ])
            ->values()
            ->all();
    }

    public function creditReturn(string $supplierId, int $amount, string $refType, string $refId, string $note): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit a positive amount.');
        }
        $this->account->post($supplierId, SupplierTransactionType::ReturnNote, -$amount, note: $note, refType: $refType, refId: $refId);
    }

    public function refundReturn(string $supplierId, int $amount, string $method, string $refType, string $refId, string $note): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund a positive amount.');
        }
        $this->account->post($supplierId, SupplierTransactionType::ReturnNote, -$amount, note: $note, refType: $refType, refId: $refId);
        $this->account->post($supplierId, SupplierTransactionType::Refund, $amount, PaymentMethod::from($method), $note, refType: $refType, refId: $refId);
    }
}
