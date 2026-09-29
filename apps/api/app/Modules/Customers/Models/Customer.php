<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Contracts\CustomerSummary;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Text\SearchText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property string $search_name
 * @property string|null $notes
 * @property int|null $credit_limit
 * @property int $balance piasters; > 0 = owes the shop
 * @property bool $is_active
 * @property Carbon|null $last_activity_at
 */
#[Fillable(['tenant_id', 'name', 'phone', 'notes', 'credit_limit', 'is_active', 'last_activity_at'])]
final class Customer extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = [
        'balance' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        self::saving(function (Customer $customer): void {
            $customer->search_name = SearchText::normalize($customer->name);
        });
    }

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'credit_limit' => 'integer',
            'is_active' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Every word must match the name, or digits match the phone (typed as 010… or +2010…).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $digits = preg_replace('/\D/', '', $term) ?? '';
        $tokens = SearchText::tokens($term);

        return $query->where(function (Builder $w) use ($digits, $tokens): void {
            $w->where(function (Builder $names) use ($tokens): void {
                foreach ($tokens as $token) {
                    $names->where('search_name', 'like', SearchText::like($token));
                }
            });
            if (strlen($digits) >= 3) {
                $w->orWhere('phone', 'like', SearchText::like(ltrim($digits, '0')));
            }
        });
    }

    public function summary(): CustomerSummary
    {
        return new CustomerSummary($this->id, $this->name, $this->phone, $this->balance, $this->credit_limit, $this->is_active);
    }

    /**
     * @return HasMany<CustomerTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class);
    }
}
