<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A supplier's invoice, posted: its stock is in and its amount is on the supplier's account.
 * Amounts are piasters.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $supplier_id
 * @property int $number
 * @property string|null $supplier_invoice_no
 * @property Carbon $invoice_date
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $paid
 * @property PaymentMethod|null $payment_method
 * @property int $returned
 * @property string|null $notes
 * @property string|null $created_by_name
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseItem> $items
 * @property-read Collection<int, PurchaseReturn> $returns
 */
#[Fillable([
    'tenant_id', 'branch_id', 'supplier_id', 'number', 'supplier_invoice_no', 'invoice_date', 'subtotal', 'discount',
    'total', 'paid', 'payment_method', 'returned', 'notes', 'created_by', 'created_by_name',
])]
final class Purchase extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'returned' => 'integer',
            'number' => 'integer',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function reference(): string
    {
        return 'PUR-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<PurchaseReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class)->orderBy('created_at')->orderBy('id');
    }
}
