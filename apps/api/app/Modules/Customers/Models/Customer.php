<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Contracts\CustomerSummary;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Text\SearchText;
use Illuminate\Contracts\Auth\Authenticatable;
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
 * @property bool|null $data_consent null = not asked (added before consent was recorded)
 * @property Carbon|null $data_consent_at
 * @property string|null $data_consent_by
 * @property string|null $data_consent_by_name
 * @property Carbon|null $erased_at personal data anonymised (financial records kept)
 * @property Carbon|null $created_at
 */
#[Fillable([
    'tenant_id', 'name', 'phone', 'notes', 'credit_limit', 'is_active', 'last_activity_at',
    'data_consent', 'data_consent_at', 'data_consent_by', 'data_consent_by_name', 'erased_at',
])]
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
            'data_consent' => 'boolean',
            'data_consent_at' => 'datetime',
            'erased_at' => 'datetime',
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

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    /** Records whether the customer agreed to having their data kept, and who asked. */
    public function recordConsent(bool $consent, ?Authenticatable $by): void
    {
        $this->data_consent = $consent;
        $this->data_consent_at = now();
        $this->data_consent_by = $by?->getAuthIdentifier();
        $this->data_consent_by_name = $by?->getAttribute('name');
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
