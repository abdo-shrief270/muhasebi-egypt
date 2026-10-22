<?php

declare(strict_types=1);

namespace App\Modules\Inquiries\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $branch_id
 * @property string $customer_id
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string $kind item | repair | phone | other
 * @property string $text
 * @property string $status open | answered | converted | closed
 * @property Carbon|null $follow_up_at
 * @property Carbon|null $reminded_at
 * @property string|null $outcome
 * @property string|null $converted_to sale | repair
 * @property string|null $created_by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'branch_id', 'customer_id', 'customer_name', 'customer_phone', 'kind', 'text', 'status', 'follow_up_at', 'reminded_at', 'outcome', 'converted_to', 'created_by', 'created_by_name'])]
final class Inquiry extends Model
{
    use BelongsToTenant, HasUuids;

    public const KINDS = ['item' => 'صنف مش موجود', 'repair' => 'سعر تصليح', 'phone' => 'موبايل', 'other' => 'حاجة تانية'];

    public const STATUSES = ['open' => 'مفتوح', 'answered' => 'اتردّ عليه', 'converted' => 'اتحوّل لبيع / تذكرة', 'closed' => 'اتقفل'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['follow_up_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'kind' => $this->kind,
            'kind_label' => self::KINDS[$this->kind] ?? $this->kind,
            'text' => $this->text,
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'overdue' => $this->status === 'open' && $this->follow_up_at !== null && $this->follow_up_at->isPast(),
            'outcome' => $this->outcome,
            'converted_to' => $this->converted_to,
            'created_by_name' => $this->created_by_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
