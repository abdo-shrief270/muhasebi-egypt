<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property bool $listed
 * @property list<string> $hidden_branches
 * @property list<int> $hidden_categories
 * @property list<string> $hidden_products
 */
#[Fillable(['tenant_id', 'listed', 'hidden_branches', 'hidden_categories', 'hidden_products'])]
final class MarketSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'market_settings';

    protected $attributes = ['listed' => true, 'hidden_branches' => '[]', 'hidden_categories' => '[]', 'hidden_products' => '[]'];

    protected function casts(): array
    {
        return [
            'listed' => 'boolean',
            'hidden_branches' => 'array',
            'hidden_categories' => 'array',
            'hidden_products' => 'array',
        ];
    }

    /** The current shop's settings (defaults when it never saved any). */
    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }
}
