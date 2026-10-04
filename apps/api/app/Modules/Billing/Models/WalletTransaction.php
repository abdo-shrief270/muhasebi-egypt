<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A move of the shop's credit (piasters) or points. Append-only.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $unit credit | points
 * @property string $type
 * @property int $amount
 * @property int $balance_after
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $note
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'unit', 'type', 'amount', 'balance_after', 'ref_type', 'ref_id', 'note', 'created_at'])]
final class WalletTransaction extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'referral' => 'محل سجّل بكودك ودفع',
        'early_renewal' => 'جدّدت قبل الميعاد',
        'yearly' => 'اشتراك سنوي',
        'onboarding' => 'خلّصت «ابدأ من هنا»',
        'convert' => 'تحويل نقاط لرصيد',
        'payment' => 'دفع اشتراك من الرصيد',
        'refund' => 'رجوع رصيد طلب دفع',
        'coupon' => 'كوبون رصيد',
        'admin' => 'من إدارة محاسبي',
    ];

    public const UPDATED_AT = null;

    protected $table = 'billing_wallet_transactions';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'unit' => $this->unit,
            'type' => $this->type,
            'type_label' => self::TYPES[$this->type] ?? $this->type,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
