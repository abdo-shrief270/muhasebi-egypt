<?php

namespace Database\Factories;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'محل '.fake()->unique()->firstName(),
            'phone' => '+2010'.fake()->unique()->numerify('########'),
            'shop_type' => ShopType::Accessories,
        ];
    }
}
